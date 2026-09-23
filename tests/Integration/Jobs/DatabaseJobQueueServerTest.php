<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Integration\Jobs;

use DateTimeImmutable;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\DatabaseJobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\DatabaseJobQueueMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\Tests\Contract\Jobs\JobQueueContractTestCase;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class DatabaseJobQueueServerTest extends JobQueueContractTestCase
{
    private Database $database;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        $dsn = getenv('MINIPORTAL_TEST_DATABASE_DSN');
        if (!is_string($dsn) || $dsn === '') {
            self::markTestSkipped('Server database integration DSN is not configured.');
        }

        $username = getenv('MINIPORTAL_TEST_DATABASE_USER');
        $password = getenv('MINIPORTAL_TEST_DATABASE_PASSWORD');
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig(
            $dsn,
            is_string($username) ? $username : null,
            is_string($password) ? $password : null,
        ));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));

        $ledger = new DatabaseMigrationLedger($this->database);
        $migration = DatabaseJobQueueMigration::definition();
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(
            DatabaseJobQueueMigration::OWNER_ID,
            [$migration],
        );
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $table = (new StorageNamespace(DatabaseJobQueueMigration::OWNER_ID))->table('queue')->value;
        $this->database->execute(new SqlStatement(sprintf('DELETE FROM %s', $table)));
    }

    protected function createQueue(): JobQueue
    {
        return new DatabaseJobQueue($this->database, $this->clock, leaseSeconds: 30, maxAttempts: 2);
    }
}
