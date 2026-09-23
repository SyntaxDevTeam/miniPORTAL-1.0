<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Worker;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;

final class JobExecution
{
    public function __construct(
        private readonly JobQueue $queue,
        private JobRecord $record,
    ) {
        if ($record->leaseToken === null) {
            throw new \InvalidArgumentException('Job execution requires an active lease token.');
        }
    }

    public function record(): JobRecord
    {
        return $this->record;
    }

    public function reportProgress(int $percent): void
    {
        $leaseToken = $this->record->leaseToken;
        if ($leaseToken === null) {
            throw new \LogicException('Job execution lost its lease token.');
        }
        $this->record = $this->queue->reportProgress($this->record->id, $leaseToken, $percent);
    }
}
