<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\RegisteredPackageRelease;

final class InMemoryPackageRegistryTest extends TestCase
{
    public function testKeepsMultipleImmutableReleaseRecordsAndOneActivePointer(): void
    {
        $registry = new InMemoryPackageRegistry(new PackageLifecycle());
        $registry->register($this->release('1.0.0'));
        $registry->register($this->release('1.1.0'));

        $this->advanceToReady($registry, '1.0.0');
        $this->advanceToReady($registry, '1.1.0');

        self::assertTrue($registry->activate('example', '1.0.0')->allowed);
        self::assertSame('1.0.0', $registry->active('example')?->version);

        self::assertTrue($registry->activate('example', '1.1.0')->allowed);
        self::assertSame('1.1.0', $registry->active('example')?->version);
        self::assertSame(
            PackageLifecycleState::Disabled,
            $registry->find('example', '1.0.0')?->state,
        );
    }

    public function testCannotActivateUnreadyRelease(): void
    {
        $registry = new InMemoryPackageRegistry(new PackageLifecycle());
        $registry->register($this->release('1.0.0'));

        $transition = $registry->activate('example', '1.0.0');

        self::assertFalse($transition->allowed);
        self::assertNull($registry->active('example'));
    }

    private function advanceToReady(InMemoryPackageRegistry $registry, string $version): void
    {
        foreach ([
            PackageLifecycleState::Validated,
            PackageLifecycleState::Staged,
            PackageLifecycleState::PreflightPassed,
            PackageLifecycleState::Ready,
        ] as $state) {
            self::assertTrue($registry->transition('example', $version, $state)->allowed);
        }
    }

    private function release(string $version): RegisteredPackageRelease
    {
        return new RegisteredPackageRelease(
            packageId: 'example',
            version: $version,
            releasePath: '/packages/example/releases/' . $version,
            checksum: hash('sha256', 'example-' . $version),
            state: PackageLifecycleState::Discovered,
        );
    }
}
