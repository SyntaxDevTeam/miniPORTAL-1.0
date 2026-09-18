<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Provider;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Support\CacheKey;

final class ArrayCache implements Cache
{
    /**
     * @var array<string, array{value: mixed, expires_at: int|null}>
     */
    private array $entries = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $normalized = CacheKey::normalize($key);

        if (!$this->has($normalized)) {
            return $default;
        }

        return $this->entries[$normalized]['value'];
    }

    public function has(string $key): bool
    {
        $normalized = CacheKey::normalize($key);
        $entry = $this->entries[$normalized] ?? null;

        if ($entry === null) {
            return false;
        }

        if ($entry['expires_at'] !== null && $entry['expires_at'] <= time()) {
            unset($this->entries[$normalized]);
            return false;
        }

        return true;
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $normalized = CacheKey::normalize($key);

        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            unset($this->entries[$normalized]);
            return;
        }

        $this->entries[$normalized] = [
            'value' => $value,
            'expires_at' => $ttlSeconds === null ? null : time() + $ttlSeconds,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->entries[CacheKey::normalize($key)]);
    }

    public function remember(string $key, Closure $producer, ?int $ttlSeconds = null): mixed
    {
        $normalized = CacheKey::normalize($key);

        if ($this->has($normalized)) {
            return $this->get($normalized);
        }

        $value = $producer();
        $this->set($normalized, $value, $ttlSeconds);

        return $value;
    }

    public function scope(string $namespace): Cache
    {
        return new NamespacedCache($this, $namespace);
    }
}
