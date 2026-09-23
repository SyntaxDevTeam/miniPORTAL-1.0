<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class ComponentIdentity
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9_.:-]{0,127}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Component identity must be a stable logical identifier.');
        }
    }
}
