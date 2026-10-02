<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
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
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetCatalog;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetProvider;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetInstance;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContext;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;

final class ActiveModuleMountTest extends TestCase
{
    public function testOnlyActiveModuleRunsAndDisableStopsItsRoute(): void
    {
        $registry = $this->registry(PackageState::Ready);
        $router = new Router();
        $router->add('GET', '/health', 'core.health', static fn (Request $_): Response => Response::text('core ok'));
        $mount = new ActiveModuleMount($registry, $router, new InMemoryLogger());
        $context = new ModuleContext(CorrelationId::fromString('request-12345678'));

        self::assertNull($mount->mount('fixture-module', $this->module(), $context));
        self::assertSame(404, $router->handle(new Request('GET', '/modules/fixture-module/status'))->status);

        $manager = new PackageLifecycleManager($registry, new PackageLifecycle());
        $manager->transition('fixture-module', '1.0.0', PackageState::Active);
        $result = $mount->mount('fixture-module', $this->module(), $context);
        self::assertNotNull($result);
        self::assertTrue($result->successful);
        self::assertSame('module ok', $router->handle(new Request('GET', '/modules/fixture-module/status'))->body);

        $manager->transition('fixture-module', '1.0.0', PackageState::Disabled);
        self::assertSame(404, $router->handle(new Request('GET', '/modules/fixture-module/status'))->status);
        self::assertSame('core ok', $router->handle(new Request('GET', '/health'))->body);
    }

    public function testFailedBootDoesNotPublishRoutes(): void
    {
        $registry = $this->registry(PackageState::Active);
        $registry->setActive('fixture-module', '1.0.0');
        $router = new Router();
        $logger = new InMemoryLogger();
        $module = new class implements Module {
            public function register(ModuleRegistration $registration): void
            {
                $registration->routes->get('/partial', 'partial', static fn (Request $_): Response => Response::text('bad'));
            }
            public function boot(ModuleContext $context): void
            {
                throw new \RuntimeException('technical secret');
            }
        };
        $result = (new ActiveModuleMount($registry, $router, $logger))
            ->mount('fixture-module', $module, new ModuleContext(CorrelationId::fromString('request-12345678')));
        self::assertFalse($result?->successful);
        self::assertSame(404, $router->handle(new Request('GET', '/modules/fixture-module/partial'))->status);
        self::assertCount(1, $logger->records);
    }

    public function testRouteExceptionIsIsolatedAndHasPublicErrorId(): void
    {
        $registry = $this->registry(PackageState::Active);
        $registry->setActive('fixture-module', '1.0.0');
        $router = new Router();
        $logger = new InMemoryLogger();
        $module = new class implements Module {
            public function register(ModuleRegistration $registration): void
            {
                $registration->routes->get('/throws', 'throws', static function (Request $_): Response {
                    throw new \RuntimeException('technical secret');
                });
            }
            public function boot(ModuleContext $context): void
            {
            }
        };
        (new ActiveModuleMount($registry, $router, $logger))
            ->mount('fixture-module', $module, new ModuleContext(CorrelationId::fromString('request-12345678')));
        $response = $router->handle(new Request('GET', '/modules/fixture-module/throws'));
        self::assertSame(503, $response->status);
        self::assertStringContainsString('Error ID:', $response->body);
        self::assertStringNotContainsString('technical secret', $response->body);
        self::assertCount(1, $logger->records);
    }

    public function testWidgetRegistrationPublishesOnlyAfterSuccessfulModuleBoot(): void
    {
        $registry = $this->registry(PackageState::Active);
        $registry->setActive('fixture-module', '1.0.0');
        $catalog = new WidgetCatalog();
        $provider = new class implements WidgetProvider {
            public function render(WidgetInstance $instance, ?RequestContext $context): array
            {
                return [new Text('Widget ready')];
            }
        };
        $module = new class($provider) implements Module {
            public function __construct(private WidgetProvider $provider)
            {
            }
            public function register(ModuleRegistration $registration): void
            {
                $registration->widgets?->register('status', $this->provider);
            }
            public function boot(ModuleContext $context): void
            {
            }
        };
        $mount = new ActiveModuleMount($registry, new Router(), new InMemoryLogger(), $catalog);
        self::assertNull($catalog->provider('fixture-module', 'status'));
        self::assertTrue($mount->mount('fixture-module', $module,
            new ModuleContext(CorrelationId::fromString('request-12345678')))?->successful);
        self::assertSame($provider, $catalog->provider('fixture-module', 'status'));
    }

    private function registry(PackageState $state): InMemoryPackageRegistry
    {
        $manifest = (new ManifestParser())->parse(json_encode([
            'schema' => 1, 'id' => 'fixture-module', 'name' => 'Fixture Module',
            'version' => '1.0.0', 'type' => 'module',
            'requires' => ['core' => '^1.0'], 'entrypoint' => 'Fixture\\Module',
        ], JSON_THROW_ON_ERROR));
        $registry = new InMemoryPackageRegistry();
        $registry->add(new PackageRelease($manifest, '/fixtures/module', $state));
        return $registry;
    }

    private function module(): Module
    {
        return new class implements Module {
            public function register(ModuleRegistration $registration): void
            {
                $registration->routes->get('/status', 'status', static fn (Request $_): Response => Response::text('module ok'));
            }
            public function boot(ModuleContext $context): void
            {
            }
        };
    }
}
