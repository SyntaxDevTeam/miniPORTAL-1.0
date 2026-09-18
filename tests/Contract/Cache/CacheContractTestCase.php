<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Cache;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;

abstract class CacheContractTestCase extends TestCase
{
    abstract protected function createCache(): Cache;

    public function testStoresRetrievesAndDeletesValues(): void
    {
        $cache = $this->createCache();

        self::assertFalse($cache->has('item'));
        self::assertSame('fallback', $cache->get('item', 'fallback'));

        $cache->set('item', ['value' => 42]);

        self::assertTrue($cache->has('item'));
        self::assertSame(['value' => 42], $cache->get('item'));

        $cache->delete('item');

        self::assertFalse($cache->has('item'));
    }

    public function testCachedNullIsDifferentFromMissingValue(): void
    {
        $cache = $this->createCache();

        $cache->set('nullable', null);

        self::assertTrue($cache->has('nullable'));
        self::assertNull($cache->get('nullable', 'fallback'));
    }

    public function testNonPositiveTtlDoesNotRetainValue(): void
    {
        $cache = $this->createCache();

        $cache->set('ephemeral', 'value', 0);

        self::assertFalse($cache->has('ephemeral'));
        self::assertSame('fallback', $cache->get('ephemeral', 'fallback'));
    }

    public function testRememberEvaluatesProducerOnlyOnMiss(): void
    {
        $cache = $this->createCache();
        $calls = 0;

        $first = $cache->remember('computed', static function () use (&$calls): string {
            $calls++;
            return 'result';
        });

        $second = $cache->remember('computed', static function () use (&$calls): string {
            $calls++;
            return 'other';
        });

        self::assertSame('result', $first);
        self::assertSame('result', $second);
        self::assertSame(1, $calls);
    }

    public function testScopesIsolateKeys(): void
    {
        $cache = $this->createCache();
        $first = $cache->scope('package.one');
        $second = $cache->scope('package.two');

        $first->set('same-key', 'first');
        $second->set('same-key', 'second');

        self::assertSame('first', $first->get('same-key'));
        self::assertSame('second', $second->get('same-key'));
        self::assertFalse($cache->has('same-key'));
    }

    public function testInvalidKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->createCache()->get('../escape');
    }
}
