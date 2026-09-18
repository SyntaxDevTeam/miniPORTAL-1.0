<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Contract;

use Closure;

interface Cache
{
    public function get(string $key, mixed $default = null): mixed;

    public function has(string $key): bool;

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void;

    public function delete(string $key): void;

    /**
     * @template T
     * @param Closure(): T $producer
     * @return T
     */
    public function remember(string $key, Closure $producer, ?int $ttlSeconds = null): mixed;

    public function scope(string $namespace): self;
}
