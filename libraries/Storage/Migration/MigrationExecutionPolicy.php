<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationExecutionPolicy
{
    public function __construct(
        public bool $allowDestructive = false,
        public bool $backupConfirmed = false,
    ) {
    }
}
