<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Contract;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;

interface JobScheduler
{
    /** @param array<string, mixed> $payload */
    public function enqueue(string $name, array $payload = [], ?string $idempotencyKey = null): JobRecord;

    public function get(string $id): ?JobRecord;
}
