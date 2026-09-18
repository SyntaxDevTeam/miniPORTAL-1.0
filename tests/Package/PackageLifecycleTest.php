<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\InvalidPackageTransition;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final class PackageLifecycleTest extends TestCase
{
    public function testReleaseCanProgressToActiveAndRegistryTracksPointer(): void
    {
        $registry = new InMemoryPackageRegistry();
        $release = new PackageRelease($this->manifest(), '/packages/example/1.0.0', PackageState::Discovered);
        $registry->add($release);

        $manager = new PackageLifecycleManager($registry, new PackageLifecycle());

        foreach ([
            PackageState::Validated,
            PackageState::Staged,
            PackageState::PreflightPassed,
            PackageState::Ready,
            PackageState::Active,
        ] as $state) {
            $manager->transition('example', '1.0.0', $state);
        }

        self::assertSame(PackageState::Active, $registry->find('example', '1.0.0')?->state);
        self::assertSame('1.0.0', $registry->active('example')?->manifest->version);
    }

    public function testIllegalTransitionIsRejectedWithoutChangingRegistry(): void
    {
        $registry = new InMemoryPackageRegistry();
        $registry->add(new PackageRelease($this->manifest(), '/packages/example/1.0.0', PackageState::Discovered));

        $manager = new PackageLifecycleManager($registry, new PackageLifecycle());

        try {
            $manager->transition('example', '1.0.0', PackageState::Active);
            self::fail('Expected invalid package transition.');
        } catch (InvalidPackageTransition) {
            self::assertSame(PackageState::Discovered, $registry->find('example', '1.0.0')?->state);
            self::assertNull($registry->active('example'));
        }
    }

    private function manifest(): PackageManifest
    {
        return new PackageManifest(
            schema: 1,
            id: 'example',
            name: 'Example',
            version: '1.0.0',
            type: PackageType::Module,
            coreConstraint: '^1.0',
            requiredCapabilities: [],
            requiredModules: [],
            providedCapabilities: [],
            entrypoint: 'Fixture\\Example',
        );
    }
}
