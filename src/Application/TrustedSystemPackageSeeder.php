<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Application;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

/** Explicit bootstrap for reviewed first-party system modules only. */
final readonly class TrustedSystemPackageSeeder
{
    public function __construct(
        private ManifestParser $parser,
        private DependencyResolver $dependencies,
        private PackageRegistry $registry,
        private PackageLifecycleManager $lifecycle,
        private PackagePreflightService $preflight,
    ) {
    }

    /** @param class-string<Module> $trustedClass */
    public function seed(string $manifestFile, string $trustedId, string $trustedClass): PackageRelease
    {
        $manifest = $this->parser->parseFile($manifestFile);
        if ($manifest->id !== $trustedId || $manifest->type !== PackageType::Module
            || $manifest->entrypoint !== $trustedClass || !is_subclass_of($trustedClass, Module::class)) {
            throw new \LogicException('Trusted system module manifest or entrypoint does not match the distribution.');
        }
        $path = realpath(dirname($manifestFile));
        if ($path === false) {
            throw new \LogicException('Trusted system module directory is missing.');
        }
        $existing = $this->registry->find($trustedId, $manifest->version);
        if ($existing !== null && ($existing->manifest != $manifest || $existing->releasePath !== $path)) {
            throw new \LogicException('Registered system module release differs from the trusted distribution.');
        }
        if ($existing === null) {
            $existing = new PackageRelease($manifest, $path, PackageState::Discovered);
            $this->registry->add($existing);
        }
        if ($this->registry->active($trustedId)?->manifest->version === $manifest->version) {
            return $existing;
        }
        $resolution = $this->dependencies->resolve([$manifest], '1.0.0');
        if (!$resolution->isSuccessful()) {
            throw new \RuntimeException('Trusted system module dependencies are not satisfied.');
        }
        $state = $existing->state;
        if ($state === PackageState::Discovered) {
            $this->lifecycle->transition($trustedId, $manifest->version, PackageState::Validated);
            $state = PackageState::Validated;
        }
        if ($state === PackageState::Validated) {
            $this->lifecycle->transition($trustedId, $manifest->version, PackageState::Staged);
            $state = PackageState::Staged;
        }
        if ($state === PackageState::Staged) {
            $report = $this->preflight->run($trustedId, $manifest->version, $resolution);
            if (!$report->passed()) {
                throw new \RuntimeException('Trusted system module failed preflight.');
            }
            $state = PackageState::PreflightPassed;
        }
        if ($state === PackageState::PreflightPassed) {
            $this->lifecycle->transition($trustedId, $manifest->version, PackageState::Ready);
            $state = PackageState::Ready;
        }
        if ($state !== PackageState::Ready) {
            throw new \LogicException(sprintf('System module cannot activate from %s.', $state->value));
        }
        return $this->lifecycle->transition($trustedId, $manifest->version, PackageState::Active);
    }
}
