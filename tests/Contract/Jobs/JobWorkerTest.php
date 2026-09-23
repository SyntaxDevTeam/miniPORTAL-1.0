<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Jobs;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobHandler;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobStatus;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\InMemoryJobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Worker\JobExecution;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Worker\JobHandlerRegistry;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Worker\JobWorker;

final class JobWorkerTest extends TestCase
{
    public function testRegisteredHandlerRunsAndCanReportProgress(): void
    {
        $queue = new InMemoryJobQueue();
        $registry = new JobHandlerRegistry();
        $handler = new class implements JobHandler {
            /** @var array<string, mixed>|null */
            public ?array $seenPayload = null;
            public function handle(JobExecution $execution): void
            {
                $this->seenPayload = $execution->record()->definition->payload;
                $execution->reportProgress(50);
            }
        };
        $registry->register('fixture', 'export', $handler);
        $job = $queue->enqueue(new JobDefinition('fixture', 'export', ['format' => 'json']));

        $result = (new JobWorker($queue, $registry))->runNext();

        self::assertNotNull($result);
        self::assertSame(JobStatus::Succeeded, $result->status);
        self::assertSame(100, $result->progressPercent);
        self::assertSame(['format' => 'json'], $handler->seenPayload);
        self::assertSame($job->id, $result->id);
    }

    public function testMissingHandlerFailsWithStableCode(): void
    {
        $queue = new InMemoryJobQueue();
        $queue->enqueue(new JobDefinition('fixture', 'missing'));

        $result = (new JobWorker($queue, new JobHandlerRegistry()))->runNext();

        self::assertNotNull($result);
        self::assertSame(JobStatus::Failed, $result->status);
        self::assertSame('handler_not_registered', $result->errorCode);
    }

    public function testHandlerExceptionIsNotExposedInJobRecord(): void
    {
        $queue = new InMemoryJobQueue();
        $registry = new JobHandlerRegistry();
        $registry->register('fixture', 'broken', new class implements JobHandler {
            public function handle(JobExecution $_execution): void
            {
                throw new \RuntimeException('secret diagnostic detail');
            }
        });
        $queue->enqueue(new JobDefinition('fixture', 'broken'));

        $result = (new JobWorker($queue, $registry))->runNext();

        self::assertNotNull($result);
        self::assertSame(JobStatus::Failed, $result->status);
        self::assertSame('handler_failed', $result->errorCode);
        self::assertStringNotContainsString('secret', (string) $result->errorCode);
    }

    public function testDuplicateHandlerRegistrationIsRejected(): void
    {
        $registry = new JobHandlerRegistry();
        $handler = new class implements JobHandler {
            public function handle(JobExecution $_execution): void
            {
            }
        };
        $registry->register('fixture', 'work', $handler);

        $this->expectException(\LogicException::class);
        $registry->register('fixture', 'work', $handler);
    }
}
