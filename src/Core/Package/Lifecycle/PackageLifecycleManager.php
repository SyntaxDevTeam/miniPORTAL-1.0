<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\AtomicPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;

final readonly class PackageLifecycleManager
{
    /** @param array<string, string> $builtInCapabilities */
    public function __construct(
        private PackageRegistry $registry,
        private PackageLifecycle $lifecycle,
        private RequiredPackagePolicy $policy = new RequiredPackagePolicy(),
        private string $coreVersion = '1.0.0',
        private array $builtInCapabilities = [],
    ) {
    }

    public function uninstall(string $packageId, string $version): void
    {
        $this->policy->assertRemoval($packageId);
        if ($this->registry->active($packageId)?->manifest->version === $version) {
            throw new \DomainException('Active package release must be disabled before uninstall.');
        }
        $this->registry->remove($packageId, $version);
    }

    /** Switch the active pointer to a retained, previously active release. */
    public function rollback(string $packageId, string $version): PackageRelease
    {
        $release = $this->registry->find($packageId, $version);
        if ($release === null || $release->state !== PackageState::Active) {
            throw new \DomainException('Rollback target must be a retained active release.');
        }
        $this->assertActiveDependencyCompatibility($release);
        $this->registry->setActive($packageId, $version);
        return $release;
    }

    public function transition(string $packageId, string $version, PackageState $target): PackageRelease
    {
        $release = $this->registry->find($packageId, $version);
        if ($release === null) {
            throw new \LogicException(sprintf('Package release %s@%s is not registered.', $packageId, $version));
        }

        $this->lifecycle->assertTransition($release->state, $target);
        $this->policy->assertTransition($packageId, $release->state, $target);
        if ($target === PackageState::Disabled && $this->registry->active($packageId)?->manifest->version === $version) {
            $this->assertNoActiveDependents($packageId);
        }
        if ($target === PackageState::Active) {
            $this->assertActiveDependencyCompatibility($release);
        }

        $wasActive = $this->registry->active($packageId)?->manifest->version === $version;
        $updated = $release->withState($target);
        $makeActive = $target === PackageState::Active;
        $clearActive = $wasActive && !in_array($target, [PackageState::Active, PackageState::Degraded], true);
        if ($this->registry instanceof AtomicPackageRegistry) {
            $this->registry->commitTransition($release, $updated, $makeActive, $clearActive);
        } else {
            $this->registry->save($updated);
            if ($makeActive) {
                $this->registry->setActive($packageId, $version);
            } elseif ($clearActive) {
                $this->registry->clearActive($packageId);
            }
        }

        return $updated;
    }

    private function assertNoActiveDependents(string $packageId): void
    {
        foreach ($this->registry->allReleases() as $candidate) {
            if ($candidate->manifest->id === $packageId
                || $this->registry->active($candidate->manifest->id)?->manifest->version !== $candidate->manifest->version) {
                continue;
            }
            if (isset($candidate->manifest->requiredModules[$packageId])) {
                throw new \DomainException(sprintf('Active module %s depends on %s.',
                    $candidate->manifest->id, $packageId));
            }
        }
    }

    public function assertActiveDependencyCompatibility(PackageRelease $release): void
    {
        $manifests = [$release->manifest];
        foreach ($this->registry->allReleases() as $candidate) {
            if ($candidate->manifest->id !== $release->manifest->id
                && $this->registry->active($candidate->manifest->id)?->manifest->version === $candidate->manifest->version) {
                $manifests[] = $candidate->manifest;
            }
        }
        $resolution = (new DependencyResolver(new VersionConstraint()))->resolve(
            $manifests, $this->coreVersion, $this->builtInCapabilities);
        if (!$resolution->isSuccessful()) {
            throw new \DomainException(sprintf('Active package set has %d dependency blocker(s).',
                count($resolution->issues)));
        }
    }
}
