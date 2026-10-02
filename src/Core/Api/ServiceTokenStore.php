<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

use DateTimeImmutable;

interface ServiceTokenStore
{
    /** @param list<string> $scopes */
    public function issue(array $scopes, DateTimeImmutable $expiresAt): IssuedServiceToken;

    public function authenticate(string $bearer, DateTimeImmutable $now): ?ServicePrincipal;

    public function revoke(string $id): void;
}
