<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolution;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;

final readonly class PackagePreflightService
{
    public function __construct(
        private PackageRegistry $registry,
        private PackagePreflightRunner $runner,
        private PackageLifecycleManager $lifecycle,
    ) {
    }

    public function run(string $packageId, string $version, DependencyResolution $resolution): PackagePreflightReport
    {
        $release = $this->registry->find($packageId, $version);
        if ($release === null) {
            throw new \LogicException(sprintf('Package release %s@%s is not registered.', $packageId, $version));
        }

        if ($release->state !== PackageState::Staged) {
            throw new \LogicException(sprintf(
                'Package release %s@%s must be staged before preflight.',
                $packageId,
                $version,
            ));
        }

        $report = $this->runner->run(new PackagePreflightContext($release, $resolution));

        $this->lifecycle->transition(
            $packageId,
            $version,
            $report->passed() ? PackageState::PreflightPassed : PackageState::FailedPreflight,
        );

        return $report;
    }
}
