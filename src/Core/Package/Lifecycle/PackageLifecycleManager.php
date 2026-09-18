<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final readonly class PackageLifecycleManager
{
    public function __construct(
        private PackageRegistry $registry,
        private PackageLifecycle $lifecycle,
    ) {
    }

    public function transition(string $packageId, string $version, PackageState $target): PackageRelease
    {
        $release = $this->registry->find($packageId, $version);
        if ($release === null) {
            throw new \LogicException(sprintf('Package release %s@%s is not registered.', $packageId, $version));
        }

        $this->lifecycle->assertTransition($release->state, $target);

        $updated = $release->withState($target);
        $this->registry->save($updated);

        if ($target === PackageState::Active) {
            $this->registry->setActive($packageId, $version);
        } elseif ($this->registry->active($packageId)?->manifest->version === $version) {
            $this->registry->clearActive($packageId);
        }

        return $updated;
    }
}
