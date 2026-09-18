<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Provider;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Support\CacheKey;

final readonly class NamespacedCache implements Cache
{
    private string $namespace;

    public function __construct(
        private Cache $cache,
        string $namespace,
    ) {
        $this->namespace = CacheKey::namespace($namespace);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->cache->get($this->key($key), $default);
    }

    public function has(string $key): bool
    {
        return $this->cache->has($this->key($key));
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $this->cache->set($this->key($key), $value, $ttlSeconds);
    }

    public function delete(string $key): void
    {
        $this->cache->delete($this->key($key));
    }

    public function remember(string $key, Closure $producer, ?int $ttlSeconds = null): mixed
    {
        return $this->cache->remember($this->key($key), $producer, $ttlSeconds);
    }

    public function scope(string $namespace): Cache
    {
        return new self(
            $this->cache,
            $this->namespace . '.' . CacheKey::namespace($namespace),
        );
    }

    private function key(string $key): string
    {
        return CacheKey::normalize($this->namespace . ':' . CacheKey::normalize($key));
    }
}
