<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Event;

interface VersionedEvent
{
    public static function eventName(): string;

    public static function eventVersion(): int;
}
