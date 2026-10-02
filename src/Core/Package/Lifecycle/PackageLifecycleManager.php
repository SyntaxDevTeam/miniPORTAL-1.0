<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\AtomicPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final readonly class PackageLifecycleManager
{
    public function __construct(
        private PackageRegistry $registry,
        private PackageLifecycle $lifecycle,
        private RequiredPackagePolicy $policy = new RequiredPackagePolicy(),
    ) {
    }

    public function transition(string $packageId, string $version, PackageState $target): PackageRelease
    {
        $release = $this->registry->find($packageId, $version);
        if ($release === null) {
            throw new \LogicException(sprintf('Package release %s@%s is not registered.', $packageId, $version));
        }

        $this->lifecycle->assertTransition($release->state, $target);
        $this->policy->assertTransition($packageId, $release->state, $target);

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
}
