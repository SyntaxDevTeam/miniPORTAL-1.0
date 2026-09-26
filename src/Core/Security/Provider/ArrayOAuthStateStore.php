<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\OAuthStateStore;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthState;

final class ArrayOAuthStateStore implements OAuthStateStore
{
    private ?OAuthState $value = null;

    public function save(OAuthState $state): void
    {
        $this->value = $state;
    }

    public function consume(string $provider, string $state, int $now): ?OAuthState
    {
        $value = $this->value;
        $this->value = null;
        if ($value === null || !hash_equals($value->provider, $provider) || !hash_equals($value->state, $state)
            || $now - $value->createdAt > 600 || $now < $value->createdAt) {
            return null;
        }
        return $value;
    }
}
