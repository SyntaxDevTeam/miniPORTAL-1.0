<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;

final class PackageLifecycleTest extends TestCase
{
    public function testHappyPathRequiresExplicitStages(): void
    {
        $lifecycle = new PackageLifecycle();

        $states = [
            PackageLifecycleState::Discovered,
            PackageLifecycleState::Validated,
            PackageLifecycleState::Staged,
            PackageLifecycleState::PreflightPassed,
            PackageLifecycleState::Ready,
            PackageLifecycleState::Active,
        ];

        for ($index = 0; $index < count($states) - 1; $index++) {
            $transition = $lifecycle->transition($states[$index], $states[$index + 1]);
            self::assertTrue($transition->allowed, $transition->reason ?? '');
        }
    }

    public function testInstallCannotSkipDirectlyToActive(): void
    {
        $transition = (new PackageLifecycle())->transition(
            PackageLifecycleState::Discovered,
            PackageLifecycleState::Active,
        );

        self::assertFalse($transition->allowed);
        self::assertNotNull($transition->reason);
    }

    public function testDegradedPackageCanRecoverOrBeDisabled(): void
    {
        $lifecycle = new PackageLifecycle();

        self::assertTrue(
            $lifecycle->transition(PackageLifecycleState::Degraded, PackageLifecycleState::Active)->allowed,
        );
        self::assertTrue(
            $lifecycle->transition(PackageLifecycleState::Degraded, PackageLifecycleState::Disabled)->allowed,
        );
    }
}
