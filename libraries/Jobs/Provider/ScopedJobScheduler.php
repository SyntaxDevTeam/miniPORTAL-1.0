<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Provider;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobScheduler;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;

final readonly class ScopedJobScheduler implements JobScheduler
{
    public function __construct(
        private JobQueue $queue,
        private string $packageId,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1) {
            throw new \InvalidArgumentException('Invalid package ID.');
        }
    }

    /** @param array<string, mixed> $payload */
    public function enqueue(string $name, array $payload = [], ?string $idempotencyKey = null): JobRecord
    {
        return $this->queue->enqueue(new JobDefinition($this->packageId, $name, $payload, $idempotencyKey));
    }

    public function get(string $id): ?JobRecord
    {
        $record = $this->queue->get($id);
        return $record?->definition->packageId === $this->packageId ? $record : null;
    }
}
