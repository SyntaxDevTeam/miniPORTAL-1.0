<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthAttemptLimiter;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthRateLimitExceeded;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArrayOAuthAttemptStore;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class OAuthAttemptLimiterTest extends TestCase
{
    public function testLimitsStartAndCallbackIndependentlyPerProvider(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00Z'));
        $limiter = new OAuthAttemptLimiter(new ArrayOAuthAttemptStore(), $clock, 2, 3, 600);

        $limiter->recordStart('github');
        $limiter->recordStart('github');
        $limiter->recordCallback('github');
        $limiter->recordStart('google');

        $this->expectException(OAuthRateLimitExceeded::class);
        $limiter->recordStart('github');
    }

    public function testWindowResetsAfterConfiguredDuration(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00Z'));
        $store = new ArrayOAuthAttemptStore();
        $limiter = new OAuthAttemptLimiter($store, $clock, 1, 1, 60);

        $limiter->recordCallback('discord');
        $clock->advance('PT60S');
        $limiter->recordCallback('discord');

        self::assertSame(2, $store->record('discord', 'callback', $clock->now()->getTimestamp(), 60));
    }
}
