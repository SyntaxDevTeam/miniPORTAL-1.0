<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Routing;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestHandler;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;

final class Router implements RequestHandler
{
    /** @var list<Route> */
    private array $routes = [];

    /** @var array<string, Route> */
    private array $routesByName = [];

    /** @param Closure(Request): Response $handler */
    public function add(string $method, string $path, string $name, Closure $handler): void
    {
        $method = strtoupper(trim($method));
        $path = $this->normalizePath($path);
        $name = trim($name);

        if ($method === '' || $name === '') {
            throw new \InvalidArgumentException('Route method and name cannot be empty.');
        }
        if (isset($this->routesByName[$name])) {
            throw new \InvalidArgumentException(sprintf('Route name %s is already registered.', $name));
        }

        foreach ($this->routes as $existing) {
            if ($existing->method === $method && $existing->path === $path) {
                throw new \InvalidArgumentException(sprintf('Route %s %s is already registered.', $method, $path));
            }
        }

        [$pattern, $parameters] = $this->compilePath($path);
        $route = new Route($method, $path, $name, $handler, $pattern, $parameters);
        $this->routes[] = $route;
        $this->routesByName[$name] = $route;
    }

    public function handle(Request $request): Response
    {
        /** @var list<string> $allowedMethods */
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            $matches = [];
            if (preg_match($route->pattern, $request->path, $matches) !== 1) {
                continue;
            }

            if ($route->method !== strtoupper($request->method)) {
                $allowedMethods[] = $route->method;
                continue;
            }

            /** @var array<string, string> $attributes */
            $attributes = [];
            foreach ($route->parameterNames as $parameterName) {
                if (isset($matches[$parameterName])) {
                    $attributes[$parameterName] = rawurldecode($matches[$parameterName]);
                }
            }

            return ($route->handler)($request->withAttributes($attributes));
        }

        if ($allowedMethods !== []) {
            $allowedMethods = array_values(array_unique($allowedMethods));
            sort($allowedMethods);

            return new Response(
                'Method Not Allowed',
                405,
                [
                    'Content-Type' => 'text/plain; charset=UTF-8',
                    'Allow' => implode(', ', $allowedMethods),
                ],
            );
        }

        return Response::text('Not Found', 404);
    }

    /** @param array<string, scalar> $parameters */
    public function url(string $name, array $parameters = []): string
    {
        $route = $this->routesByName[$name] ?? null;
        if ($route === null) {
            throw new \InvalidArgumentException(sprintf('Route %s is not registered.', $name));
        }

        $url = $route->path;
        foreach ($route->parameterNames as $parameterName) {
            if (!array_key_exists($parameterName, $parameters)) {
                throw new \InvalidArgumentException(sprintf('Missing route parameter %s.', $parameterName));
            }

            $url = str_replace(
                '{' . $parameterName . '}',
                rawurlencode((string) $parameters[$parameterName]),
                $url,
            );
        }

        return $url;
    }

    /** @return array{string, list<string>} */
    private function compilePath(string $path): array
    {
        if ($path === '/') {
            return ['#^/$#D', []];
        }

        $segments = explode('/', trim($path, '/'));
        /** @var list<string> $parameters */
        $parameters = [];
        $compiled = [];

        foreach ($segments as $segment) {
            if (preg_match('/^\{([A-Za-z][A-Za-z0-9_]*)\}$/', $segment, $matches) === 1) {
                $name = $matches[1];
                if (in_array($name, $parameters, true)) {
                    throw new \InvalidArgumentException(sprintf('Route parameter %s is declared twice.', $name));
                }

                $parameters[] = $name;
                $compiled[] = '(?P<' . $name . '>[^/]+)';
                continue;
            }

            if (str_contains($segment, '{') || str_contains($segment, '}')) {
                throw new \InvalidArgumentException('Route parameters must occupy a complete path segment.');
            }

            $compiled[] = preg_quote($segment, '#');
        }

        return ['#^/' . implode('/', $compiled) . '$#D', $parameters];
    }

    private function normalizePath(string $path): string
    {
        $path = trim($path);
        if ($path === '' || $path === '/') {
            return '/';
        }

        return '/' . trim($path, '/');
    }
}
