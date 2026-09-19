<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationMetadata
{
    public function __construct(
        public ?string $fromSchemaVersion,
        public string $toSchemaVersion,
        public string $description,
        public MigrationPhase $phase = MigrationPhase::Expand,
        public bool $destructive = false,
        public bool $requiresBackup = false,
        public ExpectedLock $expectedLock = ExpectedLock::Brief,
    ) {
        if ($fromSchemaVersion !== null && trim($fromSchemaVersion) === '') {
            throw new \InvalidArgumentException('Source schema version cannot be empty.');
        }

        if (trim($toSchemaVersion) === '') {
            throw new \InvalidArgumentException('Target schema version cannot be empty.');
        }

        if (trim($description) === '') {
            throw new \InvalidArgumentException('Migration description cannot be empty.');
        }

        if ($phase === MigrationPhase::Contract && !$destructive) {
            throw new \InvalidArgumentException('Contract migrations must be marked as destructive.');
        }
    }
}
