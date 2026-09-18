<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module\Registration;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Contract\Routing\RouteRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;

final readonly class ScopedRouteRegistrar implements RouteRegistrar
{
    private string $pathPrefix;
    private string $namePrefix;

    public function __construct(
        private Router $router,
        string $moduleId,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/', $moduleId) !== 1) {
            throw new \InvalidArgumentException('Invalid module ID for route scope.');
        }

        $this->pathPrefix = '/modules/' . str_replace('.', '/', $moduleId);
        $this->namePrefix = 'module.' . $moduleId . '.';
    }

    public function route(string $method, string $path, string $name, Closure $handler): void
    {
        $name = trim($name);
        if ($name === '' || preg_match('/^[a-z][a-z0-9._-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('Module route name must be lowercase and non-empty.');
        }

        $normalizedPath = trim($path);
        if ($normalizedPath === '' || $normalizedPath === '/') {
            $normalizedPath = '';
        } else {
            $normalizedPath = '/' . trim($normalizedPath, '/');
        }

        $this->router->add(
            $method,
            $this->pathPrefix . $normalizedPath,
            $this->namePrefix . $name,
            $handler,
        );
    }

    public function get(string $path, string $name, Closure $handler): void
    {
        $this->route('GET', $path, $name, $handler);
    }

    public function post(string $path, string $name, Closure $handler): void
    {
        $this->route('POST', $path, $name, $handler);
    }
}
