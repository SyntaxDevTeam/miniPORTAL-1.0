<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Cache;

use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\ApcuCache;

final class ApcuCacheContractTest extends CacheContractTestCase
{
    protected function setUp(): void
    {
        if (!ApcuCache::isSupported()) {
            self::markTestSkipped('APCu is unavailable for this PHP runtime.');
        }
    }

    protected function createCache(): Cache
    {
        return new ApcuCache();
    }
}
