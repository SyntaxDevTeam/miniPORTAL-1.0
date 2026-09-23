<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Jobs;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\InMemoryJobQueue;

final class InMemoryJobQueueTest extends JobQueueContractTestCase
{
    protected function createQueue(): JobQueue
    {
        return new InMemoryJobQueue();
    }

    public function testRejectsExecutablePayloadValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JobDefinition('fixture', 'work', ['callback' => static fn (): bool => true]);
    }

    public function testRejectsOversizedPayload(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JobDefinition('fixture', 'work', ['content' => str_repeat('x', 65537)]);
    }

    public function testRejectsUnboundedErrorMessage(): void
    {
        $queue = $this->createQueue();
        $job = $queue->enqueue(new JobDefinition('fixture', 'work'));
        $claimed = $queue->claimNext();
        self::assertNotNull($claimed);
        self::assertNotNull($claimed->leaseToken);

        $this->expectException(\InvalidArgumentException::class);
        $queue->fail($job->id, $claimed->leaseToken, 'secret: full diagnostic message');
    }
}
