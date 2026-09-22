<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Provider;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobScheduler;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\InvalidJobTransition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\JobNotFound;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobStatus;

/** Non-durable provider for local development and contract tests. */
final class InMemoryJobQueue implements JobQueue
{
    /** @var array<string, JobRecord> */
    private array $jobs = [];

    /** @var array<string, string> */
    private array $idempotency = [];

    public function scope(string $packageId): JobScheduler
    {
        return new ScopedJobScheduler($this, $packageId);
    }

    public function enqueue(JobDefinition $definition): JobRecord
    {
        if ($definition->idempotencyKey !== null) {
            $key = $definition->packageId . ':' . $definition->name . ':' . $definition->idempotencyKey;
            if (isset($this->idempotency[$key])) {
                return $this->jobs[$this->idempotency[$key]];
            }
        }

        $id = bin2hex(random_bytes(16));
        $record = new JobRecord($id, $definition, JobStatus::Queued);
        $this->jobs[$id] = $record;
        if (isset($key)) {
            $this->idempotency[$key] = $id;
        }

        return $record;
    }

    public function get(string $id): ?JobRecord
    {
        return $this->jobs[$id] ?? null;
    }

    public function claimNext(): ?JobRecord
    {
        foreach ($this->jobs as $id => $record) {
            if ($record->status !== JobStatus::Queued) {
                continue;
            }
            return $this->jobs[$id] = new JobRecord($id, $record->definition, JobStatus::Running);
        }

        return null;
    }

    public function reportProgress(string $id, int $percent): JobRecord
    {
        $record = $this->running($id);
        if ($percent < $record->progressPercent || $percent > 99) {
            throw new \InvalidArgumentException('Running job progress must be monotonic and below 100.');
        }

        return $this->jobs[$id] = new JobRecord($id, $record->definition, JobStatus::Running, $percent);
    }

    public function succeed(string $id): JobRecord
    {
        $record = $this->running($id);
        return $this->jobs[$id] = new JobRecord($id, $record->definition, JobStatus::Succeeded, 100);
    }

    public function fail(string $id, string $errorCode): JobRecord
    {
        $record = $this->running($id);
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $errorCode) !== 1) {
            throw new \InvalidArgumentException('Job error code must be a safe identifier.');
        }

        return $this->jobs[$id] = new JobRecord(
            $id,
            $record->definition,
            JobStatus::Failed,
            $record->progressPercent,
            $errorCode,
        );
    }

    private function running(string $id): JobRecord
    {
        $record = $this->jobs[$id] ?? throw new JobNotFound('Job does not exist.');
        if ($record->status !== JobStatus::Running) {
            throw new InvalidJobTransition('Job is not running.');
        }

        return $record;
    }
}
