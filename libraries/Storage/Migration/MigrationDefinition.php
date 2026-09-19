<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract\MigrationPreflightCheck;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class MigrationDefinition
{
    /**
     * @param list<SqlStatement> $up
     * @param list<SqlStatement> $down
     * @param list<MigrationPreflightCheck> $preflightChecks
     */
    public function __construct(
        public string $ownerId,
        public string $id,
        public MigrationMetadata $metadata,
        public array $up,
        public array $down = [],
        public array $preflightChecks = [],
    ) {
        new StorageNamespace($ownerId);

        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,127}$/', $id) !== 1) {
            throw new \InvalidArgumentException('Migration ID contains unsupported characters.');
        }

        if ($up === []) {
            throw new \InvalidArgumentException('Migration must contain at least one up statement.');
        }

        $checkIds = [];
        foreach ($preflightChecks as $check) {
            if (isset($checkIds[$check->id()])) {
                throw new \InvalidArgumentException(sprintf(
                    'Duplicate migration preflight check ID: %s.',
                    $check->id(),
                ));
            }
            $checkIds[$check->id()] = true;
        }
    }

    public function reversible(): bool
    {
        return $this->down !== [];
    }

    public function checksum(): string
    {
        $payload = [
            'owner' => $this->ownerId,
            'id' => $this->id,
            'from' => $this->metadata->fromSchemaVersion,
            'to' => $this->metadata->toSchemaVersion,
            'description' => $this->metadata->description,
            'phase' => $this->metadata->phase->value,
            'destructive' => $this->metadata->destructive,
            'requiresBackup' => $this->metadata->requiresBackup,
            'expectedLock' => $this->metadata->expectedLock->value,
            'up' => $this->statements($this->up),
            'down' => $this->statements($this->down),
            'checks' => array_map(
                static fn (MigrationPreflightCheck $check): string => $check->id(),
                $this->preflightChecks,
            ),
        ];

        $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
        return hash('sha256', $json);
    }

    /**
     * @param list<SqlStatement> $statements
     * @return list<array{sql: string, parameters: array<int|string, string|int|float|bool|null>}>
     */
    private function statements(array $statements): array
    {
        return array_map(
            static fn (SqlStatement $statement): array => [
                'sql' => $statement->sql,
                'parameters' => $statement->parameters,
            ],
            $statements,
        );
    }
}
