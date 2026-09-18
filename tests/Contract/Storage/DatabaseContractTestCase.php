<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Storage;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\NestedTransactionNotSupported;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\QueryFailed;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;

abstract class DatabaseContractTestCase extends TestCase
{
    abstract protected function database(): Database;

    protected function setUp(): void
    {
        $database = $this->database();
        $database->execute(new SqlStatement(
            'CREATE TABLE contract_items (id INTEGER PRIMARY KEY, name VARCHAR(100) NOT NULL, enabled INTEGER NOT NULL)',
        ));
    }

    public function testParameterizedExecuteAndFetchOne(): void
    {
        $database = $this->database();

        $affected = $database->execute(new SqlStatement(
            'INSERT INTO contract_items (id, name, enabled) VALUES (:id, :name, :enabled)',
            [
                'id' => 1,
                'name' => 'alpha',
                'enabled' => true,
            ],
        ));

        self::assertSame(1, $affected);

        $row = $database->fetchOne(new SqlStatement(
            'SELECT id, name, enabled FROM contract_items WHERE id = :id',
            ['id' => 1],
        ));

        self::assertNotNull($row);
        self::assertEquals(1, $row['id']);
        self::assertSame('alpha', $row['name']);
        self::assertEquals(1, $row['enabled']);
    }

    public function testPositionalParametersAndFetchAll(): void
    {
        $database = $this->database();

        $database->execute(new SqlStatement(
            'INSERT INTO contract_items (id, name, enabled) VALUES (?, ?, ?)',
            [1, 'one', 1],
        ));
        $database->execute(new SqlStatement(
            'INSERT INTO contract_items (id, name, enabled) VALUES (?, ?, ?)',
            [2, 'two', 0],
        ));

        $rows = $database->fetchAll(new SqlStatement(
            'SELECT id, name FROM contract_items WHERE id > ? ORDER BY id',
            [0],
        ));

        self::assertCount(2, $rows);
        self::assertSame(['one', 'two'], array_column($rows, 'name'));
    }

    public function testTransactionCommitsSuccessfulCallback(): void
    {
        $database = $this->database();

        $result = $database->transaction(static function (Database $transaction): string {
            self::assertTrue($transaction->isInTransaction());

            $transaction->execute(new SqlStatement(
                'INSERT INTO contract_items (id, name, enabled) VALUES (:id, :name, :enabled)',
                ['id' => 3, 'name' => 'committed', 'enabled' => 1],
            ));

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertFalse($database->isInTransaction());
        self::assertNotNull($database->fetchOne(new SqlStatement(
            'SELECT id FROM contract_items WHERE id = :id',
            ['id' => 3],
        )));
    }

    public function testTransactionRollsBackApplicationExceptionAndRethrowsIt(): void
    {
        $database = $this->database();

        try {
            $database->transaction(static function (Database $transaction): void {
                $transaction->execute(new SqlStatement(
                    'INSERT INTO contract_items (id, name, enabled) VALUES (:id, :name, :enabled)',
                    ['id' => 4, 'name' => 'rolled-back', 'enabled' => 1],
                ));

                throw new RuntimeException('application failure');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('application failure', $exception->getMessage());
        }

        self::assertFalse($database->isInTransaction());
        self::assertNull($database->fetchOne(new SqlStatement(
            'SELECT id FROM contract_items WHERE id = :id',
            ['id' => 4],
        )));
    }

    public function testNestedTransactionIsRejectedAndOuterTransactionRollsBack(): void
    {
        $database = $this->database();

        try {
            $database->transaction(static function (Database $transaction): void {
                $transaction->execute(new SqlStatement(
                    'INSERT INTO contract_items (id, name, enabled) VALUES (5, :name, 1)',
                    ['name' => 'outer'],
                ));

                $transaction->transaction(static fn (Database $_): null => null);
            });

            self::fail('Expected nested transaction rejection.');
        } catch (NestedTransactionNotSupported) {
            self::assertFalse($database->isInTransaction());
        }

        self::assertNull($database->fetchOne(new SqlStatement(
            'SELECT id FROM contract_items WHERE id = 5',
        )));
    }

    public function testProviderQueryFailureUsesStableException(): void
    {
        $this->expectException(QueryFailed::class);

        $this->database()->execute(new SqlStatement(
            'INSERT INTO missing_table (id) VALUES (:id)',
            ['id' => 1],
        ));
    }
}
