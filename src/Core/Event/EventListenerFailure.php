<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Event;

final readonly class EventListenerFailure
{
    public function __construct(
        public string $eventClass,
        public int $listenerIndex,
        public string $errorId,
    ) {
    }
}
