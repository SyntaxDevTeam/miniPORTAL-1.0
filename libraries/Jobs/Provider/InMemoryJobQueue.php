<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Provider;

use DateInterval;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;
use SyntaxDevTeam\MiniPortal\Library\Clock\Provider\SystemClock;
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

    public function __construct(
        private readonly Clock $clock = new SystemClock(),
        private readonly int $leaseSeconds = 60,
        private readonly int $maxAttempts = 3,
    ) {
        if ($leaseSeconds < 1 || $maxAttempts < 1) {
            throw new \InvalidArgumentException('Lease duration and maximum attempts must be positive.');
        }
    }

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
            $claimable = $record->status === JobStatus::Queued
                || ($record->status === JobStatus::Running && $record->leaseExpiresAt <= $this->clock->now());
            if (!$claimable) {
                continue;
            }
            if ($record->attempts >= $this->maxAttempts) {
                $this->jobs[$id] = new JobRecord(
                    $id,
                    $record->definition,
                    JobStatus::Failed,
                    $record->progressPercent,
                    'attempts_exhausted',
                    $record->attempts,
                );
                continue;
            }

            $token = bin2hex(random_bytes(16));
            return $this->jobs[$id] = new JobRecord(
                $id,
                $record->definition,
                JobStatus::Running,
                $record->progressPercent,
                attempts: $record->attempts + 1,
                leaseToken: $token,
                leaseExpiresAt: $this->clock->now()->add(new DateInterval('PT' . $this->leaseSeconds . 'S')),
            );
        }

        return null;
    }

    public function reportProgress(string $id, string $leaseToken, int $percent): JobRecord
    {
        $record = $this->leased($id, $leaseToken);
        if ($percent < $record->progressPercent || $percent > 99) {
            throw new \InvalidArgumentException('Running job progress must be monotonic and below 100.');
        }

        return $this->jobs[$id] = new JobRecord(
            $id,
            $record->definition,
            JobStatus::Running,
            $percent,
            attempts: $record->attempts,
            leaseToken: $record->leaseToken,
            leaseExpiresAt: $this->clock->now()->add(new DateInterval('PT' . $this->leaseSeconds . 'S')),
        );
    }

    public function succeed(string $id, string $leaseToken): JobRecord
    {
        $record = $this->leased($id, $leaseToken);
        return $this->jobs[$id] = new JobRecord(
            $id,
            $record->definition,
            JobStatus::Succeeded,
            100,
            attempts: $record->attempts,
        );
    }

    public function fail(string $id, string $leaseToken, string $errorCode): JobRecord
    {
        $record = $this->leased($id, $leaseToken);
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $errorCode) !== 1) {
            throw new \InvalidArgumentException('Job error code must be a safe identifier.');
        }

        return $this->jobs[$id] = new JobRecord(
            $id,
            $record->definition,
            JobStatus::Failed,
            $record->progressPercent,
            $errorCode,
            $record->attempts,
        );
    }

    private function leased(string $id, string $leaseToken): JobRecord
    {
        $record = $this->jobs[$id] ?? throw new JobNotFound('Job does not exist.');
        if ($record->status !== JobStatus::Running
            || $record->leaseToken === null
            || !hash_equals($record->leaseToken, $leaseToken)
            || $record->leaseExpiresAt === null
            || $record->leaseExpiresAt <= $this->clock->now()) {
            throw new InvalidJobTransition('Job lease is not active.');
        }

        return $record;
    }
}
