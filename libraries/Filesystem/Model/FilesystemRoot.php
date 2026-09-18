<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Model;

final readonly class FilesystemRoot
{
    public function __construct(public string $value)
    {
        if (trim($value) === '' || str_contains($value, "\0")) {
            throw new \InvalidArgumentException('Filesystem root cannot be empty or contain null bytes.');
        }
    }
}
