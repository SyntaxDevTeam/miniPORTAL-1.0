<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\MetadataPreflight;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\RegisteredPackageRelease;

final class MetadataPreflightTest extends TestCase
{
    public function testPassesForStagedCompatibleRelease(): void
    {
        $manifest = $this->manifest();
        $release = $this->release(PackageLifecycleState::Staged);

        $report = $this->preflight()->run($release, $manifest, [], '1.0.0');

        self::assertTrue($report->passed());
        self::assertCount(3, $report->checks);
    }

    public function testFailsForIdentityMismatchAndMissingCapability(): void
    {
        $manifest = new PackageManifest(
            schema: 1,
            id: 'other-package',
            name: 'Other Package',
            version: '2.0.0',
            type: PackageType::Module,
            coreConstraint: '^1.0',
            requiredCapabilities: ['filesystem' => '^1.0'],
            requiredModules: [],
            providedCapabilities: [],
            entrypoint: 'Fixture\\Entry',
        );

        $report = $this->preflight()->run(
            $this->release(PackageLifecycleState::Staged),
            $manifest,
            [],
            '1.0.0',
        );

        self::assertFalse($report->passed());
        self::assertSame('example', $report->packageId);
    }

    public function testFailsWhenReleaseWasNotStaged(): void
    {
        $report = $this->preflight()->run(
            $this->release(PackageLifecycleState::Validated),
            $this->manifest(),
            [],
            '1.0.0',
        );

        self::assertFalse($report->passed());
    }

    private function preflight(): MetadataPreflight
    {
        return new MetadataPreflight(new DependencyResolver(new VersionConstraint()));
    }

    private function release(PackageLifecycleState $state): RegisteredPackageRelease
    {
        return new RegisteredPackageRelease(
            packageId: 'example',
            version: '1.0.0',
            releasePath: '/packages/example/releases/1.0.0',
            checksum: hash('sha256', 'example'),
            state: $state,
        );
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
            entrypoint: 'Fixture\\Entry',
        );
    }
}
