<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

interface ServiceRateLimiter
{
    public function allow(string $tokenId, int $epochSeconds, int $perMinute): bool;
}
