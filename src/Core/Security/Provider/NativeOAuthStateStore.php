<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\OAuthStateStore;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthState;

final class NativeOAuthStateStore implements OAuthStateStore
{
    private const KEY = 'miniportal_oauth_state';

    public function save(OAuthState $state): void
    {
        $_SESSION[self::KEY] = [$state->provider, $state->state, $state->codeVerifier, $state->nonce, $state->createdAt];
    }

    public function consume(string $provider, string $state, int $now): ?OAuthState
    {
        $data = $_SESSION[self::KEY] ?? null;
        unset($_SESSION[self::KEY]);
        if (!is_array($data) || count($data) !== 5
            || !is_string($data[0] ?? null) || !is_string($data[1] ?? null)
            || !is_string($data[2] ?? null) || !is_string($data[3] ?? null)
            || !is_int($data[4] ?? null)) {
            return null;
        }
        try {
            $stored = new OAuthState($data[0], $data[1], $data[2], $data[3], $data[4]);
        } catch (\InvalidArgumentException) {
            return null;
        }
        if (!hash_equals($stored->provider, $provider) || !hash_equals($stored->state, $state)
            || $now - $stored->createdAt > 600 || $now < $stored->createdAt) {
            return null;
        }
        return $stored;
    }
}
