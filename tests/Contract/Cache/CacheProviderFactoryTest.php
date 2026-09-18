<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Cache;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\ApcuCache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\ArrayCache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\CacheProviderFactory;

final class CacheProviderFactoryTest extends TestCase
{
    public function testSelectsApcuWhenAvailableAndArrayOtherwise(): void
    {
        $factory = new CacheProviderFactory();
        $cache = $factory->create();

        if (ApcuCache::isSupported()) {
            self::assertInstanceOf(ApcuCache::class, $cache);
            self::assertSame('core.cache.apcu', $factory->providerId($cache));
            return;
        }

        self::assertInstanceOf(ArrayCache::class, $cache);
        self::assertSame('core.cache.array', $factory->providerId($cache));
    }
}
