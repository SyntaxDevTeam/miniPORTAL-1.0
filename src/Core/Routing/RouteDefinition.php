<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Routing;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;

final readonly class RouteDefinition
{
    /** @param Closure(Request): Response $handler */
    public function __construct(
        public string $method,
        public string $path,
        public string $name,
        public Closure $handler,
    ) {
    }
}
