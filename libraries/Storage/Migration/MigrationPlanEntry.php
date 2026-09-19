<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationPlanEntry
{
    /** @param list<MigrationPreflightResult> $preflight */
    public function __construct(
        public MigrationDefinition $migration,
        public MigrationPlanStatus $status,
        public array $preflight = [],
    ) {
    }

    public function preflightPassed(): bool
    {
        foreach ($this->preflight as $result) {
            if (!$result->passed) {
                return false;
            }
        }

        return true;
    }
}
