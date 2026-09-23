<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Worker;

use Throwable;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\InvalidJobTransition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;

final readonly class JobWorker
{
    public function __construct(
        private JobQueue $queue,
        private JobHandlerRegistry $handlers,
    ) {
    }

    public function runNext(): ?JobRecord
    {
        $claimed = $this->queue->claimNext();
        if ($claimed === null) {
            return null;
        }
        $leaseToken = $claimed->leaseToken;
        if ($leaseToken === null) {
            throw new \LogicException('Claimed job does not contain a lease token.');
        }

        $handler = $this->handlers->get($claimed->definition->packageId, $claimed->definition->name);
        if ($handler === null) {
            return $this->queue->fail($claimed->id, $leaseToken, 'handler_not_registered');
        }

        try {
            $handler->handle(new JobExecution($this->queue, $claimed));
            return $this->queue->succeed($claimed->id, $leaseToken);
        } catch (InvalidJobTransition) {
            return $this->queue->get($claimed->id);
        } catch (Throwable) {
            try {
                return $this->queue->fail($claimed->id, $leaseToken, 'handler_failed');
            } catch (InvalidJobTransition) {
                return $this->queue->get($claimed->id);
            }
        }
    }
}
