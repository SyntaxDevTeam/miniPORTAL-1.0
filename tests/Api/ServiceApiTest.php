<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Api;

use DateTimeImmutable;
use DateTimeZone;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Api\DatabaseServiceApiMigration;
use SyntaxDevTeam\MiniPortal\Core\Api\DatabaseServiceRateLimiter;
use SyntaxDevTeam\MiniPortal\Core\Api\DatabaseServiceTokenStore;
use SyntaxDevTeam\MiniPortal\Core\Api\ServiceApiGateway;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Module\ActiveModuleMount;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Module\SystemThemes\SystemThemesModule;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

final class ServiceApiTest extends TestCase
{
    public function testTokenScopeRateLimitAndModuleLifecycle(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $plan = (new MigrationPlanner($database, $ledger))->plan(
            DatabaseServiceApiMigration::OWNER_ID, [DatabaseServiceApiMigration::definition()],
        );
        (new MigrationRunner($database, $ledger))->apply($plan);
        $tokens = new DatabaseServiceTokenStore($database);
        $limits = new DatabaseServiceRateLimiter($database);
        $gateway = new ServiceApiGateway($tokens, $limits, new InMemoryLogger(), 2);
        $registry = new InMemoryPackageRegistry();
        $manifest = (new ManifestParser())->parse(json_encode([
            'schema' => 1, 'id' => 'system.themes', 'name' => 'System Themes',
            'version' => '1.0.0', 'type' => 'module', 'requires' => ['core' => '^1.0'],
            'entrypoint' => SystemThemesModule::class,
        ], JSON_THROW_ON_ERROR));
        $registry->add(new PackageRelease($manifest, '/modules/SystemThemes', PackageState::Active));
        $registry->setActive('system.themes', '1.0.0');
        $router = new Router();
        $themes = new ThemeResolver(new BaseTheme(), '1.0.0');
        self::assertTrue((new ActiveModuleMount($registry, $router, new InMemoryLogger(), apiGateway: $gateway))
            ->mount('system.themes', new SystemThemesModule($themes),
                new ModuleContext(CorrelationId::generate()))?->successful);
        $path = '/api/v1/modules/system.themes/themes';
        self::assertSame(401, $router->handle(new Request('GET', $path))->status);
        $wrongScope = $tokens->issue(['unrelated.read'], new DateTimeImmutable('+1 day', new DateTimeZone('UTC')));
        self::assertSame(403, $router->handle(new Request('GET', $path,
            headers: ['authorization' => 'Bearer ' . $wrongScope->secret]))->status);
        $issued = $tokens->issue(['themes.read'], new DateTimeImmutable('+1 day', new DateTimeZone('UTC')));
        $request = new Request('GET', $path, headers: ['authorization' => 'Bearer ' . $issued->secret]);
        $first = $router->handle($request);
        self::assertSame(200, $first->status);
        self::assertSame('application/json; charset=UTF-8', $first->headers['Content-Type']);
        self::assertStringContainsString('"base"', $first->body);
        self::assertSame(200, $router->handle($request)->status);
        self::assertSame(429, $router->handle($request)->status);
        $tokens->revoke($issued->id);
        self::assertSame(401, $router->handle($request)->status);
        $manager = new PackageLifecycleManager($registry, new PackageLifecycle());
        $manager->transition('system.themes', '1.0.0', PackageState::Disabled);
        self::assertSame(404, $router->handle($request)->status);
    }

    public function testGatewayRejectsNonJsonResponseWithoutExposingException(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $plan = (new MigrationPlanner($database, $ledger))->plan(
            DatabaseServiceApiMigration::OWNER_ID, [DatabaseServiceApiMigration::definition()],
        );
        (new MigrationRunner($database, $ledger))->apply($plan);
        $tokens = new DatabaseServiceTokenStore($database);
        $issued = $tokens->issue(['demo.read'], new DateTimeImmutable('+1 day', new DateTimeZone('UTC')));
        $logger = new InMemoryLogger();
        $gateway = new ServiceApiGateway($tokens, new DatabaseServiceRateLimiter($database), $logger);
        $response = $gateway->handle(new Request('GET', '/api/demo',
            headers: ['authorization' => 'Bearer ' . $issued->secret]), 'demo.read',
            static fn (Request $_): Response => Response::html('technical secret'));
        self::assertSame(503, $response->status);
        self::assertStringNotContainsString('technical secret', $response->body);
        self::assertCount(1, $logger->records);
    }
}
