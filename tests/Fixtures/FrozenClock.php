<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Fixtures;

use DateInterval;
use DateTimeImmutable;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;

final class FrozenClock implements Clock
{
    public function __construct(private DateTimeImmutable $time)
    {
    }

    public function now(): DateTimeImmutable
    {
        return $this->time;
    }

    public function advance(string $interval): void
    {
        $this->time = $this->time->add(new DateInterval($interval));
    }
}
