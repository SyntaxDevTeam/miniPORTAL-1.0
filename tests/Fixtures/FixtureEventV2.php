<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Fixtures;

use SyntaxDevTeam\MiniPortal\Core\Contract\Event\VersionedEvent;

final readonly class FixtureEventV2 implements VersionedEvent
{
    public function __construct(
        public string $value,
        public string $source,
    ) {
    }

    public static function eventName(): string
    {
        return 'fixture.changed';
    }

    public static function eventVersion(): int
    {
        return 2;
    }
}
