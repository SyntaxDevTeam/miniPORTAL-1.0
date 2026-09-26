<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;

interface IdentityProvider
{
    public function name(): string;
    public function label(): string;
    public function authorizationUrl(string $state, string $codeChallenge, string $nonce): string;
    public function resolveIdentity(string $code, string $codeVerifier, string $nonce): ExternalIdentity;
}
