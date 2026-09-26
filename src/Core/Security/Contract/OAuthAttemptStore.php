<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

interface OAuthAttemptStore
{
    /** Returns the number of attempts in the current window after recording this attempt. */
    public function record(string $provider, string $stage, int $now, int $windowSeconds): int;
}
