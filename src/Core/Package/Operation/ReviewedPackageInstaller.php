<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Operation;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use ReflectionClass;
use SplFileInfo;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleMigrationProvider;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;

/** Installs reviewed repository modules through a checksum-bound plan. */
final readonly class ReviewedPackageInstaller
{
    public function __construct(
        private string $projectRoot,
        private ManifestParser $parser,
        private PackageRegistry $registry,
        private PackageLifecycleManager $lifecycle,
        private PackagePreflightService $preflight,
        private DependencyResolver $dependencies,
        private Database $database,
        /** @var array<string, string> */
        private array $builtInCapabilities = [],
        private ?string $moduleSourceRoot = null,
    ) {
    }

    public function plan(string $directoryName): PackageInstallPlan
    {
        if (preg_match('/^[A-Z][A-Za-z0-9]*$/D', $directoryName) !== 1) {
            throw new \InvalidArgumentException('Module source directory name is invalid.');
        }
        $source = realpath(($this->moduleSourceRoot ?? $this->projectRoot . '/modules') . '/' . $directoryName);
        if ($source === false || !is_dir($source)) {
            throw new \InvalidArgumentException('Reviewed module source directory does not exist.');
        }
        $manifest = $this->parser->parseFile($source . '/manifest.json');
        $blockers = [];
        if ($manifest->type !== PackageType::Module) {
            $blockers[] = 'This installer accepts module packages only.';
        }
        if ($manifest->entrypoint === null || !str_starts_with($manifest->entrypoint,
            'SyntaxDevTeam\\MiniPortal\\Module\\' . $directoryName . '\\')) {
            $blockers[] = 'Entrypoint must use the module directory namespace.';
        }
        $files = $this->sourceFiles($source);
        if ($files === [] || !in_array('manifest.json', array_keys($files), true)) {
            $blockers[] = 'Module source is empty or missing its manifest.';
        }
        foreach ($files as $relative => $path) {
            if (str_ends_with($relative, '.php') && !$this->lint($path)) {
                $blockers[] = sprintf('PHP syntax check failed for %s.', $relative);
            }
        }
        if ($manifest->entrypoint !== null && !class_exists($manifest->entrypoint)) {
            $blockers[] = 'Entrypoint class cannot be loaded.';
        } elseif ($manifest->entrypoint !== null && !is_subclass_of($manifest->entrypoint, Module::class)) {
            $blockers[] = 'Entrypoint does not implement Module.';
        } elseif ($manifest->entrypoint !== null) {
            $entrypointFile = (new ReflectionClass($manifest->entrypoint))->getFileName();
            if ($entrypointFile === false || !str_starts_with((string) realpath($entrypointFile), $source . '/')) {
                $blockers[] = 'Entrypoint class is outside the reviewed source directory.';
            }
        }
        $active = [];
        foreach ($this->registry->allReleases() as $release) {
            if ($release->manifest->id !== $manifest->id
                && $this->registry->active($release->manifest->id)?->manifest->version === $release->manifest->version) {
                $active[] = $release->manifest;
            }
        }
        $resolution = $this->dependencies->resolve([$manifest, ...$active], '1.0.0', $this->builtInCapabilities);
        foreach ($resolution->issues as $issue) {
            $blockers[] = $issue->message;
        }
        if ($this->registry->find($manifest->id, $manifest->version) !== null) {
            $blockers[] = 'This package release is already registered.';
        }
        if (file_exists($this->projectRoot . '/var/packages/' . $manifest->id . '/releases/' . $manifest->version)) {
            $blockers[] = 'Immutable release directory already exists.';
        }
        $definitions = $this->migrationDefinitions($manifest->entrypoint, $manifest->id, $blockers);
        $ledger = new DatabaseMigrationLedger($this->database);
        $migrations = (new MigrationPlanner($this->database, $ledger))->plan($manifest->id, $definitions);
        $blockers = [...$blockers, ...$migrations->blockingReasons];
        foreach ($migrations->pending() as $entry) {
            if ($entry->migration->metadata->destructive || $entry->migration->metadata->requiresBackup) {
                $blockers[] = sprintf('Migration %s requires a separate reviewed backup/destructive procedure.', $entry->migration->id);
            }
        }
        $sourceChecksum = $this->fileChecksum($source, $files);
        $snapshot = [];
        foreach ($this->registry->allReleases() as $release) {
            $snapshot[] = [$release->manifest->id, $release->manifest->version, $release->state->value,
                $this->registry->active($release->manifest->id)?->manifest->version];
        }
        $checksum = hash('sha256', json_encode([$manifest->id, $manifest->version, $sourceChecksum,
            $snapshot, array_map(static fn ($entry): array => [$entry->migration->id,
                $entry->migration->checksum(), $entry->status->value], $migrations->entries)], JSON_THROW_ON_ERROR));
        return new PackageInstallPlan($manifest, $source, $sourceChecksum, $checksum, $migrations,
            array_values(array_unique($blockers)));
    }

    public function apply(string $directoryName, string $expectedChecksum): PackageRelease
    {
        $plan = $this->plan($directoryName);
        if (!hash_equals($plan->checksum, $expectedChecksum)) {
            throw new \LogicException('Module source or package state differs from the reviewed plan.');
        }
        if (!$plan->executable()) {
            throw new \DomainException(implode(' ', $plan->blockers));
        }
        $id = $plan->manifest->id;
        $version = $plan->manifest->version;
        $target = $this->projectRoot . '/var/packages/' . $id . '/releases/' . $version;
        if (file_exists($target)) {
            throw new \LogicException('Immutable release directory already exists.');
        }
        $this->copyRelease($plan->sourcePath, $target);
        if (!hash_equals($plan->sourceChecksum, $this->fileChecksum($target, $this->sourceFiles($target)))) {
            throw new \RuntimeException('Copied module release differs from the reviewed source.');
        }
        $release = new PackageRelease($plan->manifest, $target, PackageState::Discovered);
        $this->registry->add($release);
        $this->lifecycle->transition($id, $version, PackageState::Validated);
        $this->lifecycle->transition($id, $version, PackageState::Staged);
        $active = [];
        foreach ($this->registry->allReleases() as $candidate) {
            if ($candidate->manifest->id !== $id
                && $this->registry->active($candidate->manifest->id)?->manifest->version === $candidate->manifest->version) {
                $active[] = $candidate->manifest;
            }
        }
        $resolution = $this->dependencies->resolve([$plan->manifest, ...$active], '1.0.0', $this->builtInCapabilities);
        $report = $this->preflight->run($id, $version, $resolution);
        if (!$report->passed()) {
            throw new \DomainException('Package preflight failed after staging.');
        }
        try {
            (new MigrationRunner($this->database, new DatabaseMigrationLedger($this->database)))
                ->apply($plan->migrations);
        } catch (\Throwable $exception) {
            $this->lifecycle->transition($id, $version, PackageState::MigrationBlocked);
            throw $exception;
        }
        return $this->lifecycle->transition($id, $version, PackageState::Ready);
    }

    /** @param list<string> $blockers
     * @return list<MigrationDefinition>
     */
    private function migrationDefinitions(?string $entrypoint, string $id, array &$blockers): array
    {
        if ($entrypoint === null || !class_exists($entrypoint)
            || !is_subclass_of($entrypoint, ModuleMigrationProvider::class)) {
            return [];
        }
        try {
            $definitions = $entrypoint::migrations();
            foreach ($definitions as $definition) {
                if ($definition->ownerId !== $id) {
                    throw new \DomainException('Module migration has an invalid owner or type.');
                }
            }
            return $definitions;
        } catch (\Throwable $exception) {
            $blockers[] = 'Module migration catalog failed: ' . $exception->getMessage();
            return [];
        }
    }

    /** @return array<string, string> */
    private function sourceFiles(string $root): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || $file->isLink() || !$file->isFile()) {
                throw new \DomainException('Module source must contain regular files only.');
            }
            $relative = substr($file->getPathname(), strlen($root) + 1);
            if (str_contains($relative, '..') || !preg_match('/^[A-Za-z0-9_\/.+-]+$/D', $relative)) {
                throw new \DomainException('Module source contains an unsafe path.');
            }
            $files[$relative] = $file->getPathname();
        }
        ksort($files);
        return $files;
    }

    /** @param array<string, string> $files */
    private function fileChecksum(string $root, array $files): string
    {
        $hash = hash_init('sha256');
        foreach ($files as $relative => $path) {
            hash_update($hash, $relative . "\0" . (string) filesize($path) . "\0");
            hash_update_file($hash, $path);
        }
        return hash_final($hash);
    }

    private function lint(string $path): bool
    {
        $process = proc_open([PHP_BINARY, '-l', $path], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return false;
        }
        stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return proc_close($process) === 0;
    }

    private function copyRelease(string $source, string $target): void
    {
        if (!mkdir($target, 0750, true)) {
            throw new \RuntimeException('Cannot create release directory.');
        }
        foreach ($this->sourceFiles($source) as $relative => $path) {
            $destination = $target . '/' . $relative;
            $parent = dirname($destination);
            if (!is_dir($parent) && !mkdir($parent, 0750, true)) {
                throw new \RuntimeException('Cannot create release subdirectory.');
            }
            if (!copy($path, $destination)) {
                throw new \RuntimeException('Cannot copy module release file.');
            }
            chmod($destination, 0640);
        }
    }
}
