<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Realtime\Contract;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeDeliveryReport;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeEvent;

interface RealtimeChannel
{
    /** @param array<string, mixed> $payload */
    public function publish(string $channel, string $name, array $payload = []): RealtimeDeliveryReport;
    /** @param Closure(RealtimeEvent): void $listener */
    public function subscribe(string $channel, Closure $listener): string;
    public function unsubscribe(string $subscriptionId): void;
}
