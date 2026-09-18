<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module\Registration;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\RouteRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Routing\RouteDefinition;

final class BufferedModuleRouteRegistrar implements RouteRegistrar
{
    /** @var list<RouteDefinition> */
    private array $routes = [];

    public function __construct(private readonly string $moduleId)
    {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/', $moduleId) !== 1) {
            throw new \InvalidArgumentException('Invalid module ID for route namespace.');
        }
    }

    public function add(string $method, string $path, string $name, Closure $handler): void
    {
        $localName = trim($name);
        if ($localName === '' || preg_match('/^[a-z][a-z0-9._-]*$/', $localName) !== 1) {
            throw new \InvalidArgumentException('Module route name must be a non-empty lowercase identifier.');
        }

        $localPath = $this->normalizeLocalPath($path);
        $mountPath = '/modules/' . rawurlencode($this->moduleId);
        $fullPath = $localPath === '/' ? $mountPath : $mountPath . $localPath;

        $this->routes[] = new RouteDefinition(
            strtoupper(trim($method)),
            $fullPath,
            'module.' . $this->moduleId . '.' . $localName,
            $handler,
        );
    }

    public function get(string $path, string $name, Closure $handler): void
    {
        $this->add('GET', $path, $name, $handler);
    }

    public function post(string $path, string $name, Closure $handler): void
    {
        $this->add('POST', $path, $name, $handler);
    }

    /** @return list<RouteDefinition> */
    public function definitions(): array
    {
        return $this->routes;
    }

    private function normalizeLocalPath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        if (!str_starts_with($path, '/')) {
            throw new \InvalidArgumentException('Module route path must start with /.');
        }

        if (str_contains($path, '..')) {
            throw new \InvalidArgumentException('Module route path cannot contain parent traversal segments.');
        }

        return '/' . trim($path, '/');
    }
}
