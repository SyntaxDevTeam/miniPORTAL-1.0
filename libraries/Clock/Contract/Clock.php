<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Clock\Contract;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;
}
