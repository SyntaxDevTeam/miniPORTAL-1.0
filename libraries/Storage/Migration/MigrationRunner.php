<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

use DateTimeImmutable;
use DateTimeZone;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract\MigrationLedger;

final readonly class MigrationRunner
{
    public function __construct(
        private Database $database,
        private MigrationLedger $ledger,
    ) {
    }

    public function apply(
        MigrationPlan $plan,
        ?MigrationExecutionPolicy $policy = null,
    ): MigrationRunResult {
        if (!$plan->executable()) {
            throw new MigrationBlocked(sprintf(
                'Migration plan for %s is blocked: %s',
                $plan->ownerId,
                implode(' ', $plan->blockingReasons),
            ));
        }

        $policy ??= new MigrationExecutionPolicy();
        $pending = $plan->pending();
        $this->assertPlanIsCurrent($plan);

        foreach ($pending as $entry) {
            $migration = $entry->migration;
            if (!$entry->preflightPassed()) {
                throw new MigrationBlocked(sprintf(
                    'Migration %s has failed preflight checks.',
                    $migration->id,
                ));
            }

            if ($migration->metadata->destructive && !$policy->allowDestructive) {
                throw new MigrationBlocked(sprintf(
                    'Migration %s is destructive and requires explicit approval.',
                    $migration->id,
                ));
            }

            if ($migration->metadata->requiresBackup && !$policy->backupConfirmed) {
                throw new MigrationBlocked(sprintf(
                    'Migration %s requires a confirmed backup.',
                    $migration->id,
                ));
            }
        }

        $applied = [];
        foreach ($pending as $entry) {
            $migration = $entry->migration;

            try {
                foreach ($migration->up as $statement) {
                    $this->database->execute($statement);
                }

                $this->ledger->append(new MigrationRecord(
                    $migration->ownerId,
                    $migration->id,
                    $migration->checksum(),
                    $migration->metadata->toSchemaVersion,
                    $plan->batch,
                    (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
                ));
            } catch (\Throwable $throwable) {
                throw new MigrationFailed($migration->ownerId, $migration->id, $throwable);
            }

            $applied[] = $migration->id;
        }

        return new MigrationRunResult($plan->ownerId, $plan->batch, $applied);
    }

    private function assertPlanIsCurrent(MigrationPlan $plan): void
    {
        $records = [];
        foreach ($this->ledger->records($plan->ownerId) as $record) {
            $records[$record->migrationId] = $record;
        }

        foreach ($plan->entries as $entry) {
            $record = $records[$entry->migration->id] ?? null;
            if ($entry->status === MigrationPlanStatus::Pending && $record !== null) {
                throw new MigrationBlocked(sprintf(
                    'Migration plan for %s is stale; %s was already applied.',
                    $plan->ownerId,
                    $entry->migration->id,
                ));
            }

            if ($entry->status === MigrationPlanStatus::Applied
                && ($record === null || !hash_equals($record->checksum, $entry->migration->checksum()))) {
                throw new MigrationBlocked(sprintf(
                    'Migration plan for %s is stale at %s.',
                    $plan->ownerId,
                    $entry->migration->id,
                ));
            }
        }
    }
}
