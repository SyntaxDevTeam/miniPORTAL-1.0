<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Audit;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\DatabaseAuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\DatabaseAuditSinkMigration;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\ScopedAuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class DatabaseAuditSinkTest extends TestCase
{
    private Database $database;
    private string $table;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($this->database);
        $migration = DatabaseAuditSinkMigration::definition();
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(
            DatabaseAuditSinkMigration::OWNER_ID,
            [$migration],
        );
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $this->table = (new StorageNamespace(DatabaseAuditSinkMigration::OWNER_ID))->table('events')->value;
    }

    public function testPersistsCompleteEventWithoutFlatteningContext(): void
    {
        $trail = new ScopedAuditTrail(
            'syntax.files',
            new DatabaseAuditSink($this->database),
            new FrozenClock(new DateTimeImmutable('2026-02-03T04:05:06+00:00')),
        );
        $event = $trail->record(
            'user:42',
            'filesystem.delete',
            '/plugins/example.jar',
            AuditResult::Denied,
            'request-5678',
            ['scope_id' => 'survival', 'policy' => ['delete' => false]],
        );

        $row = $this->database->fetchOne(new SqlStatement(
            sprintf('SELECT * FROM %s WHERE id = :id', $this->table),
            ['id' => $event->id],
        ));

        self::assertNotNull($row);
        self::assertSame('syntax.files', $row['package_id']);
        self::assertSame('denied', $row['result']);
        self::assertIsString($row['context_json']);
        self::assertSame(
            ['scope_id' => 'survival', 'policy' => ['delete' => false]],
            json_decode($row['context_json'], true, 512, JSON_THROW_ON_ERROR),
        );
    }

    public function testMigrationIsOwnedAndReversible(): void
    {
        $migration = DatabaseAuditSinkMigration::definition();
        self::assertSame('core.audit', $migration->ownerId);
        self::assertTrue($migration->reversible());
    }
}
