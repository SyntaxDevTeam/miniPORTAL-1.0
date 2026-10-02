<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

final readonly class IssuedServiceToken
{
    public function __construct(public string $id, public string $secret)
    {
    }
}
