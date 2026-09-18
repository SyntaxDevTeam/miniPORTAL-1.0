<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyIssue;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyIssueCode;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolution;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\DependencyPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\EntrypointPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightRunner;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final class PackagePreflightTest extends TestCase
{
    public function testSuccessfulPreflightTransitionsRelease(): void
    {
        [$registry, $service] = $this->service();

        $report = $service->run(
            'example',
            '1.0.0',
            new DependencyResolution(['example'], [], []),
        );

        self::assertTrue($report->passed());
        self::assertSame(PackageState::PreflightPassed, $registry->find('example', '1.0.0')?->state);
    }

    public function testBlockingCheckTransitionsReleaseToFailedPreflight(): void
    {
        [$registry, $service] = $this->service();

        $issue = new DependencyIssue(
            DependencyIssueCode::MissingCapability,
            'example',
            'filesystem',
            'filesystem missing',
        );

        $report = $service->run(
            'example',
            '1.0.0',
            new DependencyResolution(['example'], [], [$issue]),
        );

        self::assertFalse($report->passed());
        self::assertSame(PackageState::FailedPreflight, $registry->find('example', '1.0.0')?->state);
    }

    /** @return array{InMemoryPackageRegistry, PackagePreflightService} */
    private function service(): array
    {
        $registry = new InMemoryPackageRegistry();
        $registry->add(new PackageRelease($this->manifest(), '/packages/example/1.0.0', PackageState::Staged));

        $lifecycle = new PackageLifecycleManager($registry, new PackageLifecycle());
        $runner = new PackagePreflightRunner([
            new DependencyPreflightCheck(),
            new EntrypointPreflightCheck(),
        ]);

        return [$registry, new PackagePreflightService($registry, $runner, $lifecycle)];
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
