<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Application;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Application\TrustedSystemPackageSeeder;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\DependencyPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\EntrypointPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightRunner;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistryMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Module\SystemThemes\SystemThemesModule;

final class TrustedSystemPackageSeederTest extends TestCase
{
    public function testSeedsRequiredModuleExplicitlyAndCannotDisableIt(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $plan = (new MigrationPlanner($database, $ledger))->plan(
            DatabasePackageRegistryMigration::OWNER_ID, [DatabasePackageRegistryMigration::definition()],
        );
        (new MigrationRunner($database, $ledger))->apply($plan);
        $parser = new ManifestParser();
        $registry = new DatabasePackageRegistry($database, $parser);
        $manager = new PackageLifecycleManager(
            $registry, new PackageLifecycle(), new RequiredPackagePolicy(['system.themes']),
        );
        $seeder = new TrustedSystemPackageSeeder(
            $parser,
            new DependencyResolver(new VersionConstraint()),
            $registry,
            $manager,
            new PackagePreflightService(
                $registry,
                new PackagePreflightRunner([new DependencyPreflightCheck(), new EntrypointPreflightCheck()]),
                $manager,
            ),
        );
        $path = dirname(__DIR__, 2) . '/modules/SystemThemes/manifest.json';
        self::assertSame(PackageState::Active, $seeder->seed($path, 'system.themes', SystemThemesModule::class)->state);
        self::assertSame(PackageState::Active, $seeder->seed($path, 'system.themes', SystemThemesModule::class)->state);
        self::assertSame('1.0.0', $registry->active('system.themes')?->manifest->version);
        $this->expectException(\DomainException::class);
        $manager->transition('system.themes', '1.0.0', PackageState::Disabled);
    }
}
