<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Event;

final readonly class EventListenerFailure
{
    public function __construct(
        public string $eventContract,
        public int $listenerIndex,
        public string $errorId,
    ) {
    }
}
