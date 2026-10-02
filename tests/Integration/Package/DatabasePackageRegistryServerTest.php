<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Integration\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistryMigration;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class DatabasePackageRegistryServerTest extends TestCase
{
    public function testMigrationAndActivePointerOnConfiguredServer(): void
    {
        $dsn = getenv('TEST_DATABASE_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Server database integration DSN is not configured.');
        }
        $username = getenv('TEST_DATABASE_USER');
        $password = getenv('TEST_DATABASE_PASSWORD');
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig(
            $dsn,
            is_string($username) ? $username : null,
            is_string($password) ? $password : null,
        ));
        $ledger = new DatabaseMigrationLedger($database);
        $definition = DatabasePackageRegistryMigration::definition();
        $plan = (new MigrationPlanner($database, $ledger))->plan(DatabasePackageRegistryMigration::OWNER_ID, [$definition]);
        (new MigrationRunner($database, $ledger))->apply($plan);

        $parser = new ManifestParser();
        $id = 'fixture-' . bin2hex(random_bytes(8));
        $manifest = $parser->parse(json_encode([
            'schema' => 1, 'id' => $id, 'name' => 'Registry fixture',
            'version' => '1.0.0', 'type' => 'module', 'requires' => ['core' => '^1.0'],
            'entrypoint' => 'Fixture\\Module',
        ], JSON_THROW_ON_ERROR));
        $registry = new DatabasePackageRegistry($database, $parser);
        $registry->add(new PackageRelease($manifest, '/fixtures/' . $id, PackageState::Discovered));
        $manager = new PackageLifecycleManager($registry, new PackageLifecycle());
        foreach ([PackageState::Validated, PackageState::Staged, PackageState::PreflightPassed,
            PackageState::Ready, PackageState::Active] as $state) {
            $manager->transition($id, '1.0.0', $state);
        }
        self::assertSame('1.0.0', (new DatabasePackageRegistry($database, $parser))->active($id)?->manifest->version);
        $manager->transition($id, '1.0.0', PackageState::Disabled);
        self::assertNull((new DatabasePackageRegistry($database, $parser))->active($id));
    }
}
