<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Provider;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Exception\CacheOperationFailed;
use SyntaxDevTeam\MiniPortal\Library\Cache\Exception\CacheUnavailable;
use SyntaxDevTeam\MiniPortal\Library\Cache\Support\CacheKey;

final class ApcuCache implements Cache
{
    private const PREFIX = 'miniportal:';

    public function __construct()
    {
        if (!self::isSupported()) {
            throw new CacheUnavailable('APCu is not available for the current PHP runtime.');
        }
    }

    public static function isSupported(): bool
    {
        foreach (['apcu_exists', 'apcu_fetch', 'apcu_store', 'apcu_delete'] as $function) {
            if (!function_exists($function)) {
                return false;
            }
        }

        if (!self::iniEnabled('apc.enabled')) {
            return false;
        }

        return PHP_SAPI !== 'cli' || self::iniEnabled('apc.enable_cli');
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $storageKey = $this->storageKey($key);

        if (!$this->exists($storageKey)) {
            return $default;
        }

        return $this->invoke('apcu_fetch', [$storageKey]);
    }

    public function has(string $key): bool
    {
        return $this->exists($this->storageKey($key));
    }

    public function set(string $key, mixed $value, ?int $ttlSeconds = null): void
    {
        $storageKey = $this->storageKey($key);

        if ($ttlSeconds !== null && $ttlSeconds <= 0) {
            $this->delete($key);
            return;
        }

        $stored = $this->invoke(
            'apcu_store',
            [$storageKey, $value, $ttlSeconds ?? 0],
        );

        if ($stored !== true) {
            throw new CacheOperationFailed(sprintf('APCu failed to store key %s.', $key));
        }
    }

    public function delete(string $key): void
    {
        $storageKey = $this->storageKey($key);

        if (!$this->exists($storageKey)) {
            return;
        }

        $deleted = $this->invoke('apcu_delete', [$storageKey]);

        if ($deleted !== true) {
            throw new CacheOperationFailed(sprintf('APCu failed to delete key %s.', $key));
        }
    }

    public function remember(string $key, Closure $producer, ?int $ttlSeconds = null): mixed
    {
        if ($this->has($key)) {
            return $this->get($key);
        }

        $value = $producer();
        $this->set($key, $value, $ttlSeconds);

        return $value;
    }

    public function scope(string $namespace): Cache
    {
        return new NamespacedCache($this, $namespace);
    }

    private function storageKey(string $key): string
    {
        return self::PREFIX . CacheKey::normalize($key);
    }

    private function exists(string $storageKey): bool
    {
        return $this->invoke('apcu_exists', [$storageKey]) === true;
    }

    /**
     * @param list<mixed> $arguments
     */
    private function invoke(string $function, array $arguments): mixed
    {
        if (!is_callable($function)) {
            throw new CacheUnavailable(sprintf('APCu function %s is unavailable.', $function));
        }

        return call_user_func_array($function, $arguments);
    }

    private static function iniEnabled(string $option): bool
    {
        $value = ini_get($option);
        if ($value === false) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOL);
    }
}
