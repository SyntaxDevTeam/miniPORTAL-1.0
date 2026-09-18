<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Cache\Provider;

use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;

final class CacheProviderFactory
{
    public function create(): Cache
    {
        if (ApcuCache::isSupported()) {
            return new ApcuCache();
        }

        return new ArrayCache();
    }

    public function providerId(Cache $cache): string
    {
        return match (true) {
            $cache instanceof ApcuCache => 'core.cache.apcu',
            $cache instanceof ArrayCache => 'core.cache.array',
            $cache instanceof NullCache => 'core.cache.null',
            default => throw new \LogicException(sprintf(
                'Unknown cache provider %s.',
                $cache::class,
            )),
        };
    }
}
