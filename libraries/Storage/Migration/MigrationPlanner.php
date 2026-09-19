<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract\MigrationLedger;

final readonly class MigrationPlanner
{
    public function __construct(
        private Database $database,
        private MigrationLedger $ledger,
    ) {
    }

    /**
     * @param list<MigrationDefinition> $migrations Ordered oldest to newest.
     */
    public function plan(string $ownerId, array $migrations): MigrationPlan
    {
        $this->ledger->initialize();
        $records = $this->recordsById($this->ledger->records($ownerId));
        $definitions = [];
        $entries = [];
        $blocking = [];
        $seenPending = false;
        $previousTarget = null;

        foreach ($migrations as $migration) {
            if ($migration->ownerId !== $ownerId) {
                throw new \InvalidArgumentException(sprintf(
                    'Migration %s belongs to %s instead of %s.',
                    $migration->id,
                    $migration->ownerId,
                    $ownerId,
                ));
            }

            if (isset($definitions[$migration->id])) {
                throw new \InvalidArgumentException(sprintf('Duplicate migration ID: %s.', $migration->id));
            }
            $definitions[$migration->id] = true;

            if ($migration->metadata->fromSchemaVersion !== $previousTarget) {
                $blocking[] = sprintf(
                    'Migration %s expects schema %s after %s.',
                    $migration->id,
                    $migration->metadata->fromSchemaVersion ?? '<fresh>',
                    $previousTarget ?? '<fresh>',
                );
            }
            $previousTarget = $migration->metadata->toSchemaVersion;

            $record = $records[$migration->id] ?? null;
            if ($record !== null) {
                $status = hash_equals($record->checksum, $migration->checksum())
                    ? MigrationPlanStatus::Applied
                    : MigrationPlanStatus::ChecksumMismatch;

                if ($seenPending) {
                    $blocking[] = sprintf('Applied migration %s appears after a pending migration.', $migration->id);
                }
                if ($status === MigrationPlanStatus::ChecksumMismatch) {
                    $blocking[] = sprintf('Applied migration %s has checksum drift.', $migration->id);
                }
                if ($record->schemaVersion !== $migration->metadata->toSchemaVersion) {
                    $blocking[] = sprintf(
                        'Applied migration %s has ledger schema version %s instead of %s.',
                        $migration->id,
                        $record->schemaVersion,
                        $migration->metadata->toSchemaVersion,
                    );
                }

                $entries[] = new MigrationPlanEntry($migration, $status);
                continue;
            }

            $seenPending = true;
            $preflight = [];
            foreach ($migration->preflightChecks as $check) {
                try {
                    $result = $check->check($this->database, $migration);
                } catch (\Throwable $throwable) {
                    $result = MigrationPreflightResult::failed(
                        $check->id(),
                        sprintf('Preflight check crashed: %s', $throwable->getMessage()),
                    );
                }

                $preflight[] = $result;
                if (!$result->passed) {
                    $blocking[] = sprintf(
                        'Migration %s failed preflight check %s: %s',
                        $migration->id,
                        $result->checkId,
                        $result->message,
                    );
                }
            }
            $entries[] = new MigrationPlanEntry($migration, MigrationPlanStatus::Pending, $preflight);
        }

        foreach ($records as $record) {
            if (!isset($definitions[$record->migrationId])) {
                $blocking[] = sprintf(
                    'Applied migration %s is missing from package %s.',
                    $record->migrationId,
                    $ownerId,
                );
            }
        }

        return new MigrationPlan(
            $ownerId,
            $this->ledger->nextBatch(),
            $entries,
            array_values(array_unique($blocking)),
        );
    }

    /**
     * @param list<MigrationRecord> $records
     * @return array<string, MigrationRecord>
     */
    private function recordsById(array $records): array
    {
        $indexed = [];
        foreach ($records as $record) {
            $indexed[$record->migrationId] = $record;
        }

        return $indexed;
    }
}
