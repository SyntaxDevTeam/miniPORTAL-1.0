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
        return $this->handlerAt(0)->handle($request);
    }

    private function handlerAt(int $index): RequestHandler
    {
        $middleware = $this->middleware[$index] ?? null;
        if ($middleware === null) {
            return $this->fallback;
        }

        return new class($middleware, $this, $index + 1) implements RequestHandler {
            public function __construct(
                private readonly Middleware $middleware,
                private readonly MiddlewarePipeline $pipeline,
                private readonly int $nextIndex,
            ) {
            }

            public function handle(Request $request): Response
            {
                return $this->middleware->process(
                    $request,
                    $this->pipeline->handlerAt($this->nextIndex),
                );
            }
        };
    }
}
