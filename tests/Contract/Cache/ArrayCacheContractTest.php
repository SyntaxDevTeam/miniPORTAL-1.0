<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Cache;

use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\ArrayCache;

final class ArrayCacheContractTest extends CacheContractTestCase
{
    protected function createCache(): Cache
    {
        return new ArrayCache();
    }
}
