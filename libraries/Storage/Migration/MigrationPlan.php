<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationPlan
{
    /**
     * @param list<MigrationPlanEntry> $entries
     * @param list<string> $blockingReasons
     */
    public function __construct(
        public string $ownerId,
        public int $batch,
        public array $entries,
        public array $blockingReasons = [],
    ) {
    }

    public function executable(): bool
    {
        return $this->blockingReasons === [];
    }

    /** @return list<MigrationPlanEntry> */
    public function pending(): array
    {
        return array_values(array_filter(
            $this->entries,
            static fn (MigrationPlanEntry $entry): bool => $entry->status === MigrationPlanStatus::Pending,
        ));
    }
}
