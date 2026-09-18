<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleExecutionPhase;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;

final class ModuleRegistrarTest extends TestCase
{
    public function testSuccessfulRegistrationNamespacesModuleRoutes(): void
    {
        $router = new Router();
        $registrar = new ModuleRegistrar($router, new InMemoryLogger());

        $module = new class implements Module {
            public function register(ModuleRegistration $registration): void
            {
                $registration->routes->get(
                    '/status/{name}',
                    'status',
                    static fn (Request $request): Response => Response::text($request->attribute('name') ?? 'missing'),
                );
            }

            public function boot(ModuleContext $context): void
            {
            }
        };

        $result = $registrar->register('example-module', $module);
        $response = $router->handle(new Request('GET', '/modules/example-module/status/node-1'));

        self::assertTrue($result->successful);
        self::assertSame(ModuleExecutionPhase::Registration, $result->phase);
        self::assertSame('node-1', $response->body);
        self::assertSame(
            '/modules/example-module/status/node-2',
            $router->url('module.example-module.status', ['name' => 'node-2']),
        );
    }

    public function testFailedRegistrationDoesNotPartiallyMutateRouter(): void
    {
        $router = new Router();
        $logger = new InMemoryLogger();
        $registrar = new ModuleRegistrar($router, $logger);

        $module = new class implements Module {
            public function register(ModuleRegistration $registration): void
            {
                $registration->routes->get(
                    '/partial',
                    'partial',
                    static fn (Request $_): Response => Response::text('should never exist'),
                );

                throw new RuntimeException('registration exploded');
            }

            public function boot(ModuleContext $context): void
            {
            }
        };

        $result = $registrar->register('broken-module', $module);

        self::assertFalse($result->successful);
        self::assertSame(404, $router->handle(new Request('GET', '/modules/broken-module/partial'))->status);
        self::assertCount(1, $logger->records);
    }
}
