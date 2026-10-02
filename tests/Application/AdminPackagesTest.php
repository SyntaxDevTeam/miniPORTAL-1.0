<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Application;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Application\AdminPackages;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArraySessionStore;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

final class AdminPackagesTest extends TestCase
{
    public function testInventoryRequiresLoginAndDisplaysActiveRequiredRelease(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-02T12:00:00Z'));
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $plan = (new MigrationPlanner($database, $ledger))->plan(
            DatabaseIdentityMigration::OWNER_ID, [DatabaseIdentityMigration::definition()],
        );
        (new MigrationRunner($database, $ledger))->apply($plan);
        $accounts = new DatabaseIdentityAccountRepository($database, $clock);
        $authentication = new AuthenticationManager(
            new AuthenticationSettings([
                new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
            ], [], 1800, 28800),
            new ArraySessionStore(),
            $clock,
            $accounts,
        );
        $registry = new InMemoryPackageRegistry();
        $manifest = new PackageManifest(1, 'system.themes', 'System Themes', '1.0.0',
            PackageType::Module, '^1.0', [], [], [], 'Fixture\\SystemThemes');
        $release = new PackageRelease($manifest, '/packages/system.themes', PackageState::Active);
        $registry->add($release);
        $registry->setActive('system.themes', '1.0.0');
        $router = new Router();
        (new AdminPackages($authentication, $registry, new RequiredPackagePolicy(['system.themes']),
            new ThemeResolver(new BaseTheme(), '1.0.0')))->register($router);
        self::assertSame(303, $router->handle(new Request('GET', '/admin/modules'))->status);
        $owner = $accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));
        self::assertTrue($authentication->login($owner));
        $response = $router->handle(new Request('GET', '/admin/modules'));
        self::assertSame(200, $response->status);
        self::assertStringContainsString('system.themes', $response->body);
        self::assertStringContainsString('Aktywna wersja: tak', $response->body);
        self::assertStringContainsString('Wymagany moduł systemowy: tak', $response->body);
        self::assertSame('private, no-store', $response->headers['Cache-Control']);
    }
}
