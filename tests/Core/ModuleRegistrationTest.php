<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Module\Registration\DefaultModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Module\Registration\ScopedRouteRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;

final class ModuleRegistrationTest extends TestCase
{
    public function testModuleRoutesArePathAndNameScoped(): void
    {
        $router = new Router();
        $registration = new DefaultModuleRegistration(
            new ScopedRouteRegistrar($router, 'example.admin'),
        );

        $registration->routes()->get(
            '/status/{id}',
            'status',
            static fn (Request $request): Response => Response::text($request->attribute('id') ?? 'missing'),
        );

        $response = $router->handle(new Request('GET', '/modules/example/admin/status/server-1'));

        self::assertSame(200, $response->status);
        self::assertSame('server-1', $response->body);
        self::assertSame(
            '/modules/example/admin/status/server-1',
            $router->url('module.example.admin.status', ['id' => 'server-1']),
        );
    }
}
