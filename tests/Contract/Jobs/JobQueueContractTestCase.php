<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Jobs;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\InvalidJobTransition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobStatus;

abstract class JobQueueContractTestCase extends TestCase
{
    abstract protected function createQueue(): JobQueue;

    public function testClaimsInOrderAndTracksProgress(): void
    {
        $queue = $this->createQueue();
        $first = $queue->enqueue(new JobDefinition('fixture', 'first', ['count' => 1]));
        $second = $queue->enqueue(new JobDefinition('fixture', 'second'));

        self::assertSame(JobStatus::Queued, $first->status);
        $claimedFirst = $queue->claimNext();
        $claimedSecond = $queue->claimNext();
        self::assertInstanceOf(JobRecord::class, $claimedFirst);
        self::assertInstanceOf(JobRecord::class, $claimedSecond);
        self::assertSame($first->id, $claimedFirst->id);
        self::assertSame($second->id, $claimedSecond->id);
        self::assertNull($queue->claimNext());

        self::assertSame(50, $queue->reportProgress($first->id, 50)->progressPercent);
        self::assertSame(JobStatus::Succeeded, $queue->succeed($first->id)->status);
        self::assertSame(100, $queue->get($first->id)?->progressPercent);
        self::assertSame(JobStatus::Failed, $queue->fail($second->id, 'worker_failed')->status);
        self::assertSame('worker_failed', $queue->get($second->id)?->errorCode);
    }

    public function testIdempotencyIsScopedToPackageAndJobName(): void
    {
        $queue = $this->createQueue();
        $first = $queue->enqueue(new JobDefinition('alpha', 'export', [], 'request-1'));

        self::assertSame($first->id, $queue->enqueue(new JobDefinition('alpha', 'export', [], 'request-1'))->id);
        self::assertNotSame($first->id, $queue->enqueue(new JobDefinition('beta', 'export', [], 'request-1'))->id);
        self::assertNotSame($first->id, $queue->enqueue(new JobDefinition('alpha', 'import', [], 'request-1'))->id);
    }

    public function testScopedSchedulerCannotReadAnotherPackageJob(): void
    {
        $queue = $this->createQueue();
        $alpha = $queue->scope('syntax.alpha');
        $beta = $queue->scope('beta');
        $job = $alpha->enqueue('export', ['format' => 'json']);

        self::assertSame('syntax.alpha', $job->definition->packageId);
        self::assertSame($job->id, $alpha->get($job->id)?->id);
        self::assertNull($beta->get($job->id));
    }

    public function testCannotCompleteUnclaimedJob(): void
    {
        $queue = $this->createQueue();
        $job = $queue->enqueue(new JobDefinition('fixture', 'work'));

        $this->expectException(InvalidJobTransition::class);
        $queue->succeed($job->id);
    }

    public function testProgressCannotMoveBackwards(): void
    {
        $queue = $this->createQueue();
        $job = $queue->enqueue(new JobDefinition('fixture', 'work'));
        $queue->claimNext();
        $queue->reportProgress($job->id, 60);

        $this->expectException(\InvalidArgumentException::class);
        $queue->reportProgress($job->id, 59);
    }
}
