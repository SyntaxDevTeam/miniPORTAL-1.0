<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract\MigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;

final readonly class DatabaseMigrationLedger implements MigrationLedger
{
    private const TABLE = 'miniportal_schema_migrations';

    public function __construct(private Database $database)
    {
    }

    public function initialize(): void
    {
        $this->database->execute(new SqlStatement(sprintf(
            'CREATE TABLE IF NOT EXISTS %s ('
            . 'owner_id VARCHAR(190) NOT NULL, '
            . 'migration_id VARCHAR(190) NOT NULL, '
            . 'checksum CHAR(64) NOT NULL, '
            . 'schema_version VARCHAR(64) NOT NULL, '
            . 'batch INTEGER NOT NULL, '
            . 'applied_at VARCHAR(40) NOT NULL, '
            . 'PRIMARY KEY (owner_id, migration_id)'
            . ')',
            self::TABLE,
        )));
    }

    public function records(string $ownerId): array
    {
        $rows = $this->database->fetchAll(new SqlStatement(sprintf(
            'SELECT owner_id, migration_id, checksum, schema_version, batch, applied_at '
            . 'FROM %s WHERE owner_id = :owner_id ORDER BY batch ASC, migration_id ASC',
            self::TABLE,
        ), ['owner_id' => $ownerId]));

        return array_map(
            fn (array $row): MigrationRecord => new MigrationRecord(
                $this->stringValue($row, 'owner_id'),
                $this->stringValue($row, 'migration_id'),
                $this->stringValue($row, 'checksum'),
                $this->stringValue($row, 'schema_version'),
                $this->integerValue($row, 'batch'),
                $this->stringValue($row, 'applied_at'),
            ),
            $rows,
        );
    }

    public function nextBatch(): int
    {
        $row = $this->database->fetchOne(new SqlStatement(sprintf(
            'SELECT MAX(batch) AS latest_batch FROM %s',
            self::TABLE,
        )));

        if ($row === null || $row['latest_batch'] === null) {
            return 1;
        }

        return $this->integerValue($row, 'latest_batch') + 1;
    }

    public function append(MigrationRecord $record): void
    {
        $this->database->execute(new SqlStatement(sprintf(
            'INSERT INTO %s '
            . '(owner_id, migration_id, checksum, schema_version, batch, applied_at) '
            . 'VALUES (:owner_id, :migration_id, :checksum, :schema_version, :batch, :applied_at)',
            self::TABLE,
        ), [
            'owner_id' => $record->ownerId,
            'migration_id' => $record->migrationId,
            'checksum' => $record->checksum,
            'schema_version' => $record->schemaVersion,
            'batch' => $record->batch,
            'applied_at' => $record->appliedAt,
        ]));
    }

    /** @param array<string, mixed> $row */
    private function stringValue(array $row, string $column): string
    {
        $value = $row[$column] ?? null;
        if (!is_string($value)) {
            throw new \UnexpectedValueException(sprintf(
                'Migration ledger column %s must contain a string.',
                $column,
            ));
        }

        return $value;
    }

    /** @param array<string, mixed> $row */
    private function integerValue(array $row, string $column): int
    {
        $value = $row[$column] ?? null;
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[0-9]+$/', $value) === 1) {
            return (int) $value;
        }

        throw new \UnexpectedValueException(sprintf(
            'Migration ledger column %s must contain an integer.',
            $column,
        ));
    }
}
