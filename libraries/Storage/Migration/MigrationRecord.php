<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationRecord
{
    public function __construct(
        public string $ownerId,
        public string $migrationId,
        public string $checksum,
        public string $schemaVersion,
        public int $batch,
        public string $appliedAt,
    ) {
    }
}
