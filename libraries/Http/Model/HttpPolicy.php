<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Http\Model;

final readonly class HttpPolicy
{
    public function __construct(
        public float $timeoutSeconds = 5.0,
        public int $maxAttempts = 1,
    ) {
        if ($timeoutSeconds <= 0 || $timeoutSeconds > 30 || $maxAttempts < 1 || $maxAttempts > 3) {
            throw new \InvalidArgumentException('HTTP timeout or attempt count is outside the allowed range.');
        }
    }
}
