<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Integration\Audit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\DatabaseAuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\DatabaseAuditSinkMigration;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\ScopedAuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class DatabaseAuditSinkServerTest extends TestCase
{
    public function testPersistsAuditEventOnConfiguredServerDatabase(): void
    {
        $dsn = getenv('MINIPORTAL_TEST_DATABASE_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Server database integration DSN is not configured.');
        }
        $username = getenv('MINIPORTAL_TEST_DATABASE_USER');
        $password = getenv('MINIPORTAL_TEST_DATABASE_PASSWORD');
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig(
            $dsn,
            is_string($username) ? $username : null,
            is_string($password) ? $password : null,
        ));
        $ledger = new DatabaseMigrationLedger($database);
        $migration = DatabaseAuditSinkMigration::definition();
        $plan = (new MigrationPlanner($database, $ledger))->plan(DatabaseAuditSinkMigration::OWNER_ID, [$migration]);
        (new MigrationRunner($database, $ledger))->apply($plan);
        $table = (new StorageNamespace(DatabaseAuditSinkMigration::OWNER_ID))->table('events')->value;
        $database->execute(new SqlStatement(sprintf('DELETE FROM %s', $table)));

        $event = (new ScopedAuditTrail(
            'fixture',
            new DatabaseAuditSink($database),
            new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        ))->record('system', 'integration.write', 'audit:fixture', AuditResult::Succeeded, 'request-1234');

        self::assertNotNull($database->fetchOne(new SqlStatement(
            sprintf('SELECT id FROM %s WHERE id = :id', $table),
            ['id' => $event->id],
        )));
    }
}
