<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Realtime\Contract;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeDeliveryReport;
use SyntaxDevTeam\MiniPortal\Library\Realtime\Model\RealtimeEvent;

interface RealtimeBus
{
    public function scope(string $packageId): RealtimeChannel;
    public function publish(RealtimeEvent $event): RealtimeDeliveryReport;
    /** @param Closure(RealtimeEvent): void $listener */
    public function subscribe(string $packageId, string $channel, Closure $listener): string;
    public function unsubscribe(string $subscriptionId): void;
}
