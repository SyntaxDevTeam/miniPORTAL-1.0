<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Provider;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Support\CacheKey;

final class NullCache implements Cache
{
    public function get(string $key, mixed $default = null): mixed
    {
        CacheKey::normalize($key);
        return $default;
    }

    public function has(string $key): bool
    {
        CacheKey::normalize($key);
        return false;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        CacheKey::normalize($key);
    }

    public function delete(string $key): void
    {
        CacheKey::normalize($key);
    }

    public function remember(string $key, Closure $producer, ?int $ttlSeconds = null): mixed
    {
        CacheKey::normalize($key);
        return $producer();
    }

    public function scope(string $namespace): Cache
    {
        CacheKey::namespace($namespace);
        return $this;
    }
}
