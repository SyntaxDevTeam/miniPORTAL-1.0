<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Event;

use SyntaxDevTeam\MiniPortal\Core\Contract\Event\VersionedEvent;

final readonly class EventContractId
{
    public function __construct(
        public string $name,
        public int $version,
    ) {
        if (preg_match('/^[a-z][a-z0-9.-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException('Event contract name must use lowercase letters, digits, dots and dashes.');
        }

        if ($version < 1) {
            throw new \InvalidArgumentException('Event contract version must be at least 1.');
        }
    }

    /** @param class-string<VersionedEvent> $eventClass */
    public static function fromEventClass(string $eventClass): self
    {
        return new self($eventClass::eventName(), $eventClass::eventVersion());
    }

    public function key(): string
    {
        return $this->name . '@' . $this->version;
    }
}
