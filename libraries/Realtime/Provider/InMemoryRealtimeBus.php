<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Realtime\Provider;

use Closure;
use Throwable;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Contract\RealtimeBus;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Contract\RealtimeChannel;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeDeliveryReport;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeEvent;

/** Request-local provider for development and contract tests. */
final class InMemoryRealtimeBus implements RealtimeBus
{
    /** @var array<string, array{packageId: string, channel: string, listener: Closure(RealtimeEvent): void}> */
    private array $subscriptions = [];

    public function scope(string $packageId): RealtimeChannel
    {
        return new ScopedRealtimeChannel($this, $packageId);
    }

    public function publish(RealtimeEvent $event): RealtimeDeliveryReport
    {
        $delivered = 0;
        $failed = 0;
        foreach ($this->subscriptions as $subscription) {
            if ($subscription['packageId'] !== $event->packageId || $subscription['channel'] !== $event->channel) {
                continue;
            }
            try {
                ($subscription['listener'])($event);
                $delivered++;
            } catch (Throwable) {
                $failed++;
            }
        }
        return new RealtimeDeliveryReport($event, $delivered, $failed);
    }

    public function subscribe(string $packageId, string $channel, Closure $listener): string
    {
        $this->assertAddress($packageId, $channel);
        $id = bin2hex(random_bytes(16));
        $this->subscriptions[$id] = ['packageId' => $packageId, 'channel' => $channel, 'listener' => $listener];
        return $id;
    }

    public function unsubscribe(string $subscriptionId): void
    {
        unset($this->subscriptions[$subscriptionId]);
    }

    private function assertAddress(string $packageId, string $channel): void
    {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1
            || preg_match('/^[a-z][a-z0-9_.:-]{0,127}$/D', $channel) !== 1) {
            throw new \InvalidArgumentException('Invalid realtime package or channel.');
        }
    }
}
