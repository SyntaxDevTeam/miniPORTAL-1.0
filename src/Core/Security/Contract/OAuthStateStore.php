<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

use SyntaxDevTeam\MiniPortal\Core\Security\OAuthState;

interface OAuthStateStore
{
    public function save(OAuthState $state): void;
    public function consume(string $provider, string $state, int $now): ?OAuthState;
}
