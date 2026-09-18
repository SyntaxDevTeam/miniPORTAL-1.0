<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Http\Middleware;
use SyntaxDevTeam\MiniPortal\Core\Http\MiddlewarePipeline;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestHandler;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;

final class MiddlewarePipelineTest extends TestCase
{
    public function testMiddlewareCanDecorateRequestBeforeFallback(): void
    {
        $middleware = new class implements Middleware {
            public function process(Request $request, RequestHandler $next): Response
            {
                return $next->handle($request->withAttributes(['source' => 'middleware']));
            }
        };

        $fallback = new class implements RequestHandler {
            public function handle(Request $request): Response
            {
                return Response::text($request->attribute('source') ?? 'missing');
            }
        };

        $response = (new MiddlewarePipeline([$middleware], $fallback))
            ->handle(new Request('GET', '/'));

        self::assertSame('middleware', $response->body);
    }
}
