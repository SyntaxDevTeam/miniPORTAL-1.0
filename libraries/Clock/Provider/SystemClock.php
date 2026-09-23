<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Clock\Provider;

use DateTimeImmutable;
use DateTimeZone;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;

final class SystemClock implements Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
}
