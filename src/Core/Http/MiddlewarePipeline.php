<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

final readonly class MiddlewarePipeline implements RequestHandler
{
    /** @param list<Middleware> $middleware */
    public function __construct(
        private array $middleware,
        private RequestHandler $fallback,
    ) {
    }

    public function handle(Request $request): Response
    {
        $handler = $this->fallback;

        foreach (array_reverse($this->middleware) as $middleware) {
            $next = $handler;
            $handler = new class($middleware, $next) implements RequestHandler {
                public function __construct(
                    private readonly Middleware $middleware,
                    private readonly RequestHandler $next,
                ) {
                }

                public function handle(Request $request): Response
                {
                    return $this->middleware->process($request, $this->next);
                }
            };
        }

        return $handler->handle($request);
    }
}
