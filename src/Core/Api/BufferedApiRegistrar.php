<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

use Closure;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ApiRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Routing\RouteDefinition;

/** Buffers versioned module endpoints until the module successfully boots. */
final class BufferedApiRegistrar implements ApiRegistrar
{
    /** @var list<RouteDefinition> */
    private array $definitions = [];

    public function __construct(private readonly string $moduleId, private readonly ServiceApiGateway $gateway)
    {
    }

    public function add(string $method, string $path, string $name, string $requiredScope, Closure $handler): void
    {
        if (!in_array(strtoupper($method), ['GET', 'POST'], true)
            || preg_match('/^[a-z][a-z0-9._-]*$/D', $name) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{1,119}$/D', $requiredScope) !== 1
            || $path === '' || $path[0] !== '/' || str_contains($path, '..') || str_contains($path, '//')) {
            throw new \InvalidArgumentException('API endpoint declaration is invalid.');
        }
        $mount = '/api/v1/modules/' . rawurlencode($this->moduleId);
        $full = $path === '/' ? $mount : $mount . rtrim($path, '/');
        $gateway = $this->gateway;
        $this->definitions[] = new RouteDefinition(strtoupper($method), $full,
            'api.v1.' . $this->moduleId . '.' . $name,
            static fn (Request $request): Response => $gateway->handle($request, $requiredScope, $handler));
    }

    public function get(string $path, string $name, string $requiredScope, Closure $handler): void
    {
        $this->add('GET', $path, $name, $requiredScope, $handler);
    }

    public function post(string $path, string $name, string $requiredScope, Closure $handler): void
    {
        $this->add('POST', $path, $name, $requiredScope, $handler);
    }

    /** @return list<RouteDefinition> */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
