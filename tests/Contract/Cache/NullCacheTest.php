<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Cache;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\NullCache;

final class NullCacheTest extends TestCase
{
    public function testNeverRetainsValuesAndAlwaysEvaluatesRememberProducer(): void
    {
        $cache = new NullCache();
        $calls = 0;

        $cache->set('item', 'value');

        self::assertFalse($cache->has('item'));
        self::assertSame('fallback', $cache->get('item', 'fallback'));

        self::assertSame('first', $cache->remember('item', static function () use (&$calls): string {
            $calls++;
            return 'first';
        }));

        self::assertSame('second', $cache->remember('item', static function () use (&$calls): string {
            $calls++;
            return 'second';
        }));

        self::assertSame(2, $calls);
    }
}
