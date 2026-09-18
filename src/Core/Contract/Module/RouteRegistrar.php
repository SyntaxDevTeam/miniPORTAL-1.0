<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;

interface RouteRegistrar
{
    /** @param Closure(Request): Response $handler */
    public function add(string $method, string $path, string $name, Closure $handler): void;

    /** @param Closure(Request): Response $handler */
    public function get(string $path, string $name, Closure $handler): void;

    /** @param Closure(Request): Response $handler */
    public function post(string $path, string $name, Closure $handler): void;
}
