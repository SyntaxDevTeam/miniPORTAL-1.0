<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationRunResult
{
    /** @param list<string> $appliedMigrationIds */
    public function __construct(
        public string $ownerId,
        public int $batch,
        public array $appliedMigrationIds,
    ) {
    }
}
