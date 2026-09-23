<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Realtime\Model;

final readonly class RealtimeDeliveryReport
{
    public function __construct(
        public RealtimeEvent $event,
        public int $delivered,
        public int $failed,
    ) {
        if ($delivered < 0 || $failed < 0) {
            throw new \InvalidArgumentException('Realtime delivery counters cannot be negative.');
        }
    }
}
