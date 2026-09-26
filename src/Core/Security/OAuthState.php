<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

final readonly class OAuthState
{
    public function __construct(
        public string $provider,
        public string $state,
        public string $codeVerifier,
        public string $nonce,
        public int $createdAt,
    ) {
        if ($provider === '' || $createdAt < 0
            || preg_match('/^[a-f0-9]{64}$/D', $state) !== 1
            || preg_match('/^[A-Za-z0-9_-]{43,128}$/D', $codeVerifier) !== 1
            || preg_match('/^[a-f0-9]{64}$/D', $nonce) !== 1) {
            throw new \InvalidArgumentException('OAuth state is invalid.');
        }
    }

    public function codeChallenge(): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $this->codeVerifier, true)), '+/', '-_'), '=');
    }
}
