<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Jobs;

use DateTimeImmutable;
use PDO;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\InvalidJobTransition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobStatus;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\DatabaseJobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\DatabaseJobQueueMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class DatabaseJobQueueTest extends JobQueueContractTestCase
{
    private Database $database;
    private FrozenClock $clock;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }

        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $this->clock = new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00'));
        $ledger = new DatabaseMigrationLedger($this->database);
        $migration = DatabaseJobQueueMigration::definition();
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(
            DatabaseJobQueueMigration::OWNER_ID,
            [$migration],
        );
        (new MigrationRunner($this->database, $ledger))->apply($plan);
    }

    protected function createQueue(): JobQueue
    {
        return new DatabaseJobQueue($this->database, $this->clock, leaseSeconds: 30, maxAttempts: 2);
    }

    public function testExpiredLeaseIsRecoveredAndStaleWorkerCannotMutateJob(): void
    {
        $queue = $this->createQueue();
        $job = $queue->enqueue(new JobDefinition('fixture', 'recover'));
        $firstLease = $queue->claimNext();
        self::assertNotNull($firstLease);
        self::assertNotNull($firstLease->leaseToken);

        $this->clock->advance('PT31S');
        $secondLease = $queue->claimNext();
        self::assertNotNull($secondLease);
        self::assertNotNull($secondLease->leaseToken);
        self::assertSame($job->id, $secondLease->id);
        self::assertSame(2, $secondLease->attempts);
        self::assertNotSame($firstLease->leaseToken, $secondLease->leaseToken);

        $this->expectException(InvalidJobTransition::class);
        $queue->succeed($job->id, $firstLease->leaseToken);
    }

    public function testExpiredJobFailsAfterAttemptLimit(): void
    {
        $queue = $this->createQueue();
        $job = $queue->enqueue(new JobDefinition('fixture', 'bounded'));
        self::assertNotNull($queue->claimNext());
        $this->clock->advance('PT31S');
        self::assertNotNull($queue->claimNext());
        $this->clock->advance('PT31S');

        self::assertNull($queue->claimNext());
        $failed = $queue->get($job->id);
        self::assertNotNull($failed);
        self::assertSame(JobStatus::Failed, $failed->status);
        self::assertSame('attempts_exhausted', $failed->errorCode);
    }

    public function testProgressHeartbeatRenewsLease(): void
    {
        $queue = $this->createQueue();
        $queue->enqueue(new JobDefinition('fixture', 'heartbeat'));
        $claimed = $queue->claimNext();
        self::assertNotNull($claimed);
        self::assertNotNull($claimed->leaseToken);

        $this->clock->advance('PT20S');
        $renewed = $queue->reportProgress($claimed->id, $claimed->leaseToken, 25);
        self::assertNotNull($renewed->leaseExpiresAt);
        $this->clock->advance('PT20S');

        self::assertNull($queue->claimNext());
        self::assertSame(1, $queue->get($claimed->id)?->attempts);
    }

    public function testMigrationIsPlanFirstAndOwnedByCoreJobs(): void
    {
        $migration = DatabaseJobQueueMigration::definition();

        self::assertSame('core.jobs', $migration->ownerId);
        self::assertSame('001-create-job-queue', $migration->id);
        self::assertTrue($migration->reversible());
    }
}
