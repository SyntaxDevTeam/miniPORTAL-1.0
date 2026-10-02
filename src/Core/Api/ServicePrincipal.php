<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

final readonly class ServicePrincipal
{
    /** @param list<string> $scopes */
    public function __construct(public string $tokenId, public array $scopes)
    {
    }

    public function hasScope(string $scope): bool
    {
        return in_array('*', $this->scopes, true) || in_array($scope, $this->scopes, true);
    }
}
