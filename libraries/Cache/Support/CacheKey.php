<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Support;

final class CacheKey
{
    private const MAX_KEY_LENGTH = 200;

    public static function normalize(string $key): string
    {
        if ($key === '' || strlen($key) > self::MAX_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Cache key must contain between 1 and %d characters.',
                self::MAX_KEY_LENGTH,
            ));
        }

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:\/-]*$/', $key) !== 1) {
            throw new \InvalidArgumentException(
                'Cache key contains unsupported characters.',
            );
        }

        return $key;
    }

    public static function namespace(string $namespace): string
    {
        if (preg_match('/^[a-z][a-z0-9.-]{0,127}$/', $namespace) !== 1) {
            throw new \InvalidArgumentException(
                'Cache namespace must use lowercase letters, digits, dots and dashes.',
            );
        }

        return $namespace;
    }
}
