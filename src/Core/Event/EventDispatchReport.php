<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Event;

final readonly class EventDispatchReport
{
    /** @param list<EventListenerFailure> $failures */
    public function __construct(
        public int $delivered,
        public array $failures,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->failures === [];
    }
}
