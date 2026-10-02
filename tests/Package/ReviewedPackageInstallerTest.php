<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Operation\ReviewedPackageInstaller;
use SyntaxDevTeam\MiniPortal\Core\Package\Operation\PackageOperator;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\DependencyPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\EntrypointPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightRunner;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class ReviewedPackageInstallerTest extends TestCase
{
    public function testPlanRequiresExactReviewedChecksumAndLeavesRegistryUntouched(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $registry = new InMemoryPackageRegistry();
        $lifecycle = new PackageLifecycleManager($registry, new PackageLifecycle());
        $targetRoot = sys_get_temp_dir() . '/miniportal-installer-' . bin2hex(random_bytes(8));
        mkdir($targetRoot);
        $installer = new ReviewedPackageInstaller(
            $targetRoot, new ManifestParser(), $registry, $lifecycle,
            new PackagePreflightService($registry,
                new PackagePreflightRunner([new DependencyPreflightCheck(), new EntrypointPreflightCheck()]), $lifecycle),
            new DependencyResolver(new VersionConstraint()), $database,
            [], dirname(__DIR__, 2) . '/modules',
        );
        try {
            $plan = $installer->plan('FixtureStatus');
            self::assertTrue($plan->executable(), implode(' ', $plan->blockers));
            self::assertSame([], $plan->migrations->pending());
            try {
                $installer->apply('FixtureStatus', str_repeat('0', 64));
                self::fail('A stale or forged checksum must block installation.');
            } catch (\LogicException) {
                self::assertSame([], $registry->allReleases());
            }
            $release = $installer->apply('FixtureStatus', $plan->checksum);
            self::assertSame(PackageState::Ready, $release->state);
            self::assertFalse($installer->plan('FixtureStatus')->executable());
            $operator = new PackageOperator($registry, $lifecycle, new RequiredPackagePolicy());
            $activation = $operator->plan('activate', 'fixture.status', '1.0.0');
            $operator->apply('activate', 'fixture.status', '1.0.0', $activation->checksum);
            $active = $registry->active('fixture.status');
            self::assertNotNull($active);
            self::assertSame('1.0.0', $active->manifest->version);
        } finally {
            $releaseRoot = $targetRoot . '/var/packages/fixture.status/releases/1.0.0';
            foreach (['StatusModule.php', 'manifest.json'] as $file) {
                if (is_file($releaseRoot . '/' . $file)) {
                    unlink($releaseRoot . '/' . $file);
                }
            }
            foreach ([$releaseRoot, dirname($releaseRoot), dirname($releaseRoot, 2),
                dirname($releaseRoot, 3), dirname($releaseRoot, 4), $targetRoot] as $directory) {
                if (is_dir($directory)) {
                    rmdir($directory);
                }
            }
        }
    }
}
