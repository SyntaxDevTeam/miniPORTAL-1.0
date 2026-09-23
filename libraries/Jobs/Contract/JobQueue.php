<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Contract;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;

interface JobQueue
{
    public function scope(string $packageId): JobScheduler;

    public function enqueue(JobDefinition $definition): JobRecord;

    public function get(string $id): ?JobRecord;

    public function claimNext(): ?JobRecord;

    public function reportProgress(string $id, string $leaseToken, int $percent): JobRecord;

    public function succeed(string $id, string $leaseToken): JobRecord;

    public function fail(string $id, string $leaseToken, string $errorCode): JobRecord;
}
