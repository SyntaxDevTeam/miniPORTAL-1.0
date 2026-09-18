<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Logging;

interface Logger
{
    /** @param array<string, scalar|null> $context */
    public function error(string $message, array $context = []): void;
}
