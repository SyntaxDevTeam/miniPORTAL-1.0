<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo;

use Closure;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\NestedTransactionNotSupported;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\QueryFailed;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\TransactionFailed;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;

final class PdoDatabase implements Database
{
    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function fetchOne(SqlStatement $statement): ?array
    {
        $prepared = $this->prepareAndExecute($statement);
        $row = $prepared->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        /** @var array<string, mixed> $row */
        return $row;
    }

    public function fetchAll(SqlStatement $statement): array
    {
        $prepared = $this->prepareAndExecute($statement);
        $rows = $prepared->fetchAll(PDO::FETCH_ASSOC);

        /** @var list<array<string, mixed>> $rows */
        return $rows;
    }

    public function execute(SqlStatement $statement): int
    {
        return $this->prepareAndExecute($statement)->rowCount();
    }

    public function transaction(Closure $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            throw new NestedTransactionNotSupported(
                'Nested database transactions are not supported by the portable storage baseline.',
            );
        }

        try {
            if (!$this->pdo->beginTransaction()) {
                throw new TransactionFailed('Unable to begin database transaction.');
            }
        } catch (PDOException $exception) {
            throw new TransactionFailed(
                'Unable to begin database transaction.',
                previous: $exception,
            );
        }

        try {
            $result = $callback($this);
        } catch (Throwable $throwable) {
            $this->rollbackAfterFailure();
            throw $throwable;
        }

        try {
            if (!$this->pdo->commit()) {
                throw new TransactionFailed('Unable to commit database transaction.');
            }
        } catch (PDOException $exception) {
            throw new TransactionFailed(
                'Unable to commit database transaction.',
                previous: $exception,
            );
        }

        return $result;
    }

    public function isInTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    private function prepareAndExecute(SqlStatement $statement): PDOStatement
    {
        try {
            $prepared = $this->pdo->prepare($statement->sql);

            foreach ($statement->parameters as $name => $value) {
                $parameter = is_int($name)
                    ? $name + 1
                    : (str_starts_with($name, ':') ? $name : ':' . $name);

                [$boundValue, $type] = $this->binding($value);
                $prepared->bindValue($parameter, $boundValue, $type);
            }

            $prepared->execute();

            return $prepared;
        } catch (PDOException $exception) {
            throw new QueryFailed(
                'Database query failed.',
                previous: $exception,
            );
        }
    }

    /**
     * @param string|int|float|bool|null $value
     * @return array{string|int|bool|null, int}
     */
    private function binding(string|int|float|bool|null $value): array
    {
        return match (true) {
            $value === null => [null, PDO::PARAM_NULL],
            is_bool($value) => [$value, PDO::PARAM_BOOL],
            is_int($value) => [$value, PDO::PARAM_INT],
            is_float($value) => [(string) $value, PDO::PARAM_STR],
            default => [$value, PDO::PARAM_STR],
        };
    }

    private function rollbackAfterFailure(): void
    {
        if (!$this->pdo->inTransaction()) {
            return;
        }

        try {
            if (!$this->pdo->rollBack()) {
                throw new TransactionFailed('Unable to roll back database transaction.');
            }
        } catch (PDOException $exception) {
            throw new TransactionFailed(
                'Unable to roll back database transaction.',
                previous: $exception,
            );
        }
    }
}
