<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Realtime;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeEvent;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Provider\InMemoryRealtimeBus;

final class InMemoryRealtimeBusTest extends TestCase
{
    public function testPublishesOnlyWithinPackageAndChannelScope(): void
    {
        $bus = new InMemoryRealtimeBus();
        $alpha = $bus->scope('syntax.alpha');
        $beta = $bus->scope('syntax.beta');
        $received = [];
        $alpha->subscribe('jobs:one', static function (RealtimeEvent $event) use (&$received): void { $received[] = $event; });
        $alpha->subscribe('jobs:two', static function (RealtimeEvent $event) use (&$received): void { $received[] = $event; });
        $beta->subscribe('jobs:one', static function (RealtimeEvent $event) use (&$received): void { $received[] = $event; });

        $report = $alpha->publish('jobs:one', 'progress', ['percent' => 40]);

        self::assertSame(1, $report->delivered);
        self::assertSame(0, $report->failed);
        self::assertCount(1, $received);
        self::assertSame('syntax.alpha', $received[0]->packageId);
        self::assertSame(['percent' => 40], $received[0]->payload);
    }

    public function testSubscriberFailureDoesNotPreventOtherDeliveries(): void
    {
        $channel = (new InMemoryRealtimeBus())->scope('fixture');
        $delivered = false;
        $channel->subscribe('status', static function (RealtimeEvent $_): void { throw new \RuntimeException('listener failure'); });
        $channel->subscribe('status', static function (RealtimeEvent $_) use (&$delivered): void { $delivered = true; });

        $report = $channel->publish('status', 'changed');

        self::assertTrue($delivered);
        self::assertSame(1, $report->delivered);
        self::assertSame(1, $report->failed);
    }

    public function testScopedChannelCannotRemoveAnotherScopeSubscription(): void
    {
        $bus = new InMemoryRealtimeBus();
        $alpha = $bus->scope('alpha');
        $beta = $bus->scope('beta');
        $deliveries = 0;
        $subscription = $alpha->subscribe('status', static function () use (&$deliveries): void { $deliveries++; });

        $beta->unsubscribe($subscription);
        $alpha->publish('status', 'changed');
        self::assertSame(1, $deliveries);

        $alpha->unsubscribe($subscription);
        $alpha->publish('status', 'changed');
        self::assertSame(1, $deliveries);
    }

    public function testRejectsExecutableAndOversizedPayloads(): void
    {
        $channel = (new InMemoryRealtimeBus())->scope('fixture');
        try {
            $channel->publish('status', 'changed', ['callback' => static fn (): bool => true]);
            self::fail('Expected executable payload rejection.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        $channel->publish('status', 'changed', ['content' => str_repeat('x', 65537)]);
    }
}
