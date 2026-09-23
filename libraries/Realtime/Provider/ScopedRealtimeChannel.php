<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Realtime\Provider;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Contract\RealtimeBus;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Contract\RealtimeChannel;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeDeliveryReport;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeEvent;

final class ScopedRealtimeChannel implements RealtimeChannel
{
    /** @var array<string, true> */
    private array $subscriptions = [];

    public function __construct(
        private readonly RealtimeBus $bus,
        private readonly string $packageId,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1) {
            throw new \InvalidArgumentException('Invalid package ID.');
        }
    }

    public function publish(string $channel, string $name, array $payload = []): RealtimeDeliveryReport
    {
        return $this->bus->publish(new RealtimeEvent(
            bin2hex(random_bytes(16)),
            $this->packageId,
            $channel,
            $name,
            $payload,
        ));
    }

    public function subscribe(string $channel, Closure $listener): string
    {
        $subscriptionId = $this->bus->subscribe($this->packageId, $channel, $listener);
        $this->subscriptions[$subscriptionId] = true;
        return $subscriptionId;
    }

    public function unsubscribe(string $subscriptionId): void
    {
        if (!isset($this->subscriptions[$subscriptionId])) {
            return;
        }
        $this->bus->unsubscribe($subscriptionId);
        unset($this->subscriptions[$subscriptionId]);
    }
}
