<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Model;

final readonly class SqlIdentifier
{
    public function __construct(public string $value)
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,62}$/', $value) !== 1) {
            throw new \InvalidArgumentException(
                'SQL identifier must use lowercase letters, digits and underscores and be at most 63 characters.',
            );
        }
    }
}
