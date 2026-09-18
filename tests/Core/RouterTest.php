<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;

final class RouterTest extends TestCase
{
    public function testDispatchesNamedRouteWithDecodedParameters(): void
    {
        $router = new Router();
        $router->add(
            'GET',
            '/servers/{server}/files/{file}',
            'files.show',
            static fn (Request $request): Response => Response::text(
                ($request->attribute('server') ?? '') . ':' . ($request->attribute('file') ?? ''),
            ),
        );

        $response = $router->handle(new Request('GET', '/servers/survival/files/server.properties'));

        self::assertSame(200, $response->status);
        self::assertSame('survival:server.properties', $response->body);
        self::assertSame(
            '/servers/my%20server/files/server.properties',
            $router->url('files.show', ['server' => 'my server', 'file' => 'server.properties']),
        );
    }

    public function testReturnsMethodNotAllowedWithAllowHeader(): void
    {
        $router = new Router();
        $router->add('POST', '/jobs', 'jobs.create', static fn (Request $_): Response => Response::text('ok'));

        $response = $router->handle(new Request('GET', '/jobs'));

        self::assertSame(405, $response->status);
        self::assertSame('POST', $response->headers['Allow'] ?? null);
    }

    public function testUnknownRouteReturns404(): void
    {
        $response = (new Router())->handle(new Request('GET', '/missing'));

        self::assertSame(404, $response->status);
    }
}
