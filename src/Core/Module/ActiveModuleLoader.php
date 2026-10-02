<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

use ReflectionClass;
use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleFactory;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleServices;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

/** Loads only active module releases; each failure remains local to its owner. */
final readonly class ActiveModuleLoader
{
    public function __construct(
        private PackageRegistry $registry,
        private ActiveModuleMount $mount,
        private Logger $logger,
        /** @var array<string, string> */
        private array $builtInCapabilities = [],
        private ?ModuleServices $services = null,
    ) {
    }

    /**
     * Distribution-owned modules may need host-provided public services at construction.
     *
     * @param array<string, Module> $trustedInstances
     * @return array<string, ModuleExecutionResult>
     */
    public function mountAll(ModuleContext $context, array $trustedInstances = []): array
    {
        $results = [];
        $active = [];
        foreach ($this->registry->allReleases() as $candidate) {
            $id = $candidate->manifest->id;
            if ($candidate->manifest->type !== PackageType::Module
                || $this->registry->active($id)?->manifest->version !== $candidate->manifest->version) {
                continue;
            }
            $active[$id] = $candidate;
        }
        $resolution = (new DependencyResolver(new VersionConstraint()))->resolve(
            array_map(static fn (PackageRelease $release) => $release->manifest, array_values($active)),
            '1.0.0', $this->builtInCapabilities);
        $blocked = [];
        foreach ($resolution->issues as $issue) {
            $blocked[$issue->packageId] = true;
        }
        foreach ($resolution->loadOrder as $id) {
            $candidate = $active[$id] ?? null;
            if ($candidate === null) {
                continue;
            }
            foreach (array_keys($candidate->manifest->requiredModules) as $dependencyId) {
                if (!($results[$dependencyId]->successful ?? false)) {
                    $blocked[$id] = true;
                }
            }
            if (isset($blocked[$id])) {
                $errorId = (string) CorrelationId::generate();
                $this->logger->error('Active module has incompatible dependencies.', [
                    'module_id' => $id, 'error_id' => $errorId,
                    'request_id' => (string) $context->correlationId,
                ]);
                $results[$id] = ModuleExecutionResult::failure($id, ModuleExecutionPhase::Registration, $errorId);
                continue;
            }
            try {
                $module = $trustedInstances[$id] ?? $this->load($candidate);
                $result = $this->mount->mount($id, $module, $context);
                if ($result !== null) {
                    $results[$id] = $result;
                }
            } catch (\Throwable $exception) {
                $errorId = (string) CorrelationId::generate();
                $this->logger->error('Active module load failed.', [
                    'module_id' => $id,
                    'error_id' => $errorId,
                    'request_id' => (string) $context->correlationId,
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]);
                $results[$id] = ModuleExecutionResult::failure($id, ModuleExecutionPhase::Registration, $errorId);
            }
        }
        return $results;
    }

    private function load(PackageRelease $release): Module
    {
        $class = $release->manifest->entrypoint;
        $root = realpath($release->releasePath);
        $prefix = 'SyntaxDevTeam\\MiniPortal\\Module\\';
        if ($root === false || $class === null || !str_starts_with($class, $prefix)) {
            throw new \DomainException('Module release path or entrypoint is invalid.');
        }
        $relative = substr($class, strlen($prefix));
        if (preg_match('/^[A-Z][A-Za-z0-9]*(?:\\\\[A-Z][A-Za-z0-9]*)+$/D', $relative) !== 1) {
            throw new \DomainException('Module entrypoint namespace is invalid.');
        }
        $parts = explode('\\', $relative);
        $modulePrefix = $prefix . array_shift($parts) . '\\';
        spl_autoload_register(static function (string $requested) use ($modulePrefix, $root): void {
            if (!str_starts_with($requested, $modulePrefix)) {
                return;
            }
            $suffix = substr($requested, strlen($modulePrefix));
            if (preg_match('/^[A-Za-z][A-Za-z0-9]*(?:\\\\[A-Za-z][A-Za-z0-9]*)*$/D', $suffix) !== 1) {
                return;
            }
            $path = realpath($root . '/' . str_replace('\\', '/', $suffix) . '.php');
            if ($path !== false && str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
                require_once $path;
            }
        }, prepend: true);
        if (!class_exists($class)) {
            throw new \DomainException('Module entrypoint class was not found in its release.');
        }
        $reflection = new ReflectionClass($class);
        $file = $reflection->getFileName();
        if ($file === false || !str_starts_with((string) realpath($file), $root . DIRECTORY_SEPARATOR)
            || !$reflection->implementsInterface(Module::class)) {
            throw new \DomainException('Module entrypoint must be a Module from its release.');
        }
        $constructor = $reflection->getConstructor();
        if ($reflection->implementsInterface(ModuleFactory::class)) {
            if ($this->services === null) {
                throw new \DomainException('Module factory services are unavailable.');
            }
            $instance = $class::create($this->services);
            if (!$instance instanceof Module) {
                throw new \LogicException('Module factory returned an invalid instance.');
            }
            return $instance;
        }
        if (!$reflection->isInstantiable()) {
            throw new \DomainException('Module entrypoint cannot be instantiated.');
        }
        if ($constructor !== null && $constructor->getNumberOfRequiredParameters() !== 0) {
            throw new \DomainException('Optional module entrypoint requires unsupported constructor services.');
        }
        $instance = $reflection->newInstance();
        if (!$instance instanceof Module) {
            throw new \LogicException('Loaded entrypoint does not implement Module.');
        }
        return $instance;
    }
}
