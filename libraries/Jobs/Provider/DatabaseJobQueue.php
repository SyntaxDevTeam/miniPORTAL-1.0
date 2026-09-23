<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Provider;

use DateInterval;
use DateTimeImmutable;
use JsonException;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobScheduler;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\InvalidJobTransition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Exception\JobNotFound;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobDefinition;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobRecord;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Model\JobStatus;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\QueryFailed;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class DatabaseJobQueue implements JobQueue
{
    private string $table;

    public function __construct(
        private Database $database,
        private Clock $clock,
        private int $leaseSeconds = 60,
        private int $maxAttempts = 3,
    ) {
        if ($leaseSeconds < 1 || $maxAttempts < 1) {
            throw new \InvalidArgumentException('Lease duration and maximum attempts must be positive.');
        }
        $this->table = (new StorageNamespace(DatabaseJobQueueMigration::OWNER_ID))->table('queue')->value;
    }

    public function scope(string $packageId): JobScheduler
    {
        return new ScopedJobScheduler($this, $packageId);
    }

    public function enqueue(JobDefinition $definition): JobRecord
    {
        if ($definition->idempotencyKey !== null) {
            $existing = $this->findIdempotent($definition);
            if ($existing !== null) {
                return $existing;
            }
        }

        $now = $this->timestamp($this->clock->now());
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $id = bin2hex(random_bytes(16));
            $orderRow = $this->database->fetchOne(new SqlStatement(
                sprintf('SELECT COALESCE(MAX(queue_order), 0) AS max_order FROM %s', $this->table),
            ));
            $queueOrder = ($orderRow === null ? 0 : $this->requiredInt($orderRow, 'max_order')) + 1;
            try {
                $this->database->execute(new SqlStatement(
                    sprintf(
                        'INSERT INTO %s (id, package_id, name, payload, idempotency_key, status, progress_percent, error_code, attempts, lease_token, lease_expires_at, queue_order, created_at, updated_at) '
                        . 'VALUES (:id, :package_id, :name, :payload, :idempotency_key, :status, 0, NULL, 0, NULL, NULL, :queue_order, :created_at, :updated_at)',
                        $this->table,
                    ),
                    [
                        'id' => $id,
                        'package_id' => $definition->packageId,
                        'name' => $definition->name,
                        'payload' => json_encode($definition->payload, JSON_THROW_ON_ERROR),
                        'idempotency_key' => $definition->idempotencyKey,
                        'status' => JobStatus::Queued->value,
                        'queue_order' => $queueOrder,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                ));
                return $this->get($id) ?? throw new JobNotFound('Newly enqueued job does not exist.');
            } catch (QueryFailed $exception) {
                $existing = $definition->idempotencyKey === null ? null : $this->findIdempotent($definition);
                if ($existing !== null) {
                    return $existing;
                }
                if ($attempt === 9) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Job enqueue retry loop exited unexpectedly.');
    }

    public function get(string $id): ?JobRecord
    {
        $row = $this->database->fetchOne(new SqlStatement(
            sprintf('SELECT * FROM %s WHERE id = :id', $this->table),
            ['id' => $id],
        ));
        return $row === null ? null : $this->hydrate($row);
    }

    public function claimNext(): ?JobRecord
    {
        $now = $this->timestamp($this->clock->now());
        $this->database->execute(new SqlStatement(
            sprintf(
                "UPDATE %s SET status = :failed, error_code = :error_code, lease_token = NULL, lease_expires_at = NULL, updated_at = :updated_at "
                . "WHERE status = :running AND lease_expires_at <= :expired_before AND attempts >= :max_attempts",
                $this->table,
            ),
            [
                'failed' => JobStatus::Failed->value,
                'error_code' => 'attempts_exhausted',
                'updated_at' => $now,
                'expired_before' => $now,
                'running' => JobStatus::Running->value,
                'max_attempts' => $this->maxAttempts,
            ],
        ));

        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = $this->database->fetchOne(new SqlStatement(
                sprintf(
                    "SELECT id FROM %s WHERE attempts < :max_attempts AND (status = :queued OR (status = :running AND lease_expires_at <= :now)) ORDER BY queue_order LIMIT 1",
                    $this->table,
                ),
                [
                    'max_attempts' => $this->maxAttempts,
                    'queued' => JobStatus::Queued->value,
                    'running' => JobStatus::Running->value,
                    'now' => $now,
                ],
            ));
            if ($candidate === null) {
                return null;
            }

            $id = $this->requiredString($candidate, 'id');
            $token = bin2hex(random_bytes(16));
            $expires = $this->timestamp($this->clock->now()->add(new DateInterval('PT' . $this->leaseSeconds . 'S')));
            $affected = $this->database->execute(new SqlStatement(
                sprintf(
                    "UPDATE %s SET status = :running_set, attempts = attempts + 1, lease_token = :lease_token, lease_expires_at = :lease_expires_at, updated_at = :updated_at "
                    . "WHERE id = :id AND attempts < :max_attempts AND (status = :queued OR (status = :running_match AND lease_expires_at <= :expired_before))",
                    $this->table,
                ),
                [
                    'running_set' => JobStatus::Running->value,
                    'lease_token' => $token,
                    'lease_expires_at' => $expires,
                    'updated_at' => $now,
                    'expired_before' => $now,
                    'id' => $id,
                    'max_attempts' => $this->maxAttempts,
                    'queued' => JobStatus::Queued->value,
                    'running_match' => JobStatus::Running->value,
                ],
            ));
            if ($affected === 1) {
                return $this->get($id);
            }
        }

        return null;
    }

    public function reportProgress(string $id, string $leaseToken, int $percent): JobRecord
    {
        if ($percent < 0 || $percent > 99) {
            throw new \InvalidArgumentException('Running job progress must be between 0 and 99.');
        }
        $record = $this->leased($id, $leaseToken);
        if ($percent < $record->progressPercent) {
            throw new \InvalidArgumentException('Running job progress must be monotonic.');
        }
        $this->mutateLease(
            $id,
            $leaseToken,
            'progress_percent = :progress, lease_expires_at = :lease_expires_at',
            [
                'progress' => $percent,
                'lease_expires_at' => $this->timestamp(
                    $this->clock->now()->add(new DateInterval('PT' . $this->leaseSeconds . 'S')),
                ),
            ],
        );
        return $this->get($id) ?? throw new JobNotFound('Job does not exist.');
    }

    public function succeed(string $id, string $leaseToken): JobRecord
    {
        $this->leased($id, $leaseToken);
        $this->mutateLease(
            $id,
            $leaseToken,
            'status = :status, progress_percent = 100, lease_token = NULL, lease_expires_at = NULL',
            ['status' => JobStatus::Succeeded->value],
        );
        return $this->get($id) ?? throw new JobNotFound('Job does not exist.');
    }

    public function fail(string $id, string $leaseToken, string $errorCode): JobRecord
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $errorCode) !== 1) {
            throw new \InvalidArgumentException('Job error code must be a safe identifier.');
        }
        $this->leased($id, $leaseToken);
        $this->mutateLease(
            $id,
            $leaseToken,
            'status = :status, error_code = :error_code, lease_token = NULL, lease_expires_at = NULL',
            ['status' => JobStatus::Failed->value, 'error_code' => $errorCode],
        );
        return $this->get($id) ?? throw new JobNotFound('Job does not exist.');
    }

    /** @param array<int|string, string|int|float|bool|null> $parameters */
    private function mutateLease(string $id, string $leaseToken, string $changes, array $parameters): void
    {
        $parameters += [
            'updated_at' => $this->timestamp($this->clock->now()),
            'id' => $id,
            'running' => JobStatus::Running->value,
            'lease_token_match' => $leaseToken,
            'now' => $this->timestamp($this->clock->now()),
        ];
        $affected = $this->database->execute(new SqlStatement(
            sprintf(
                'UPDATE %s SET %s, updated_at = :updated_at WHERE id = :id AND status = :running AND lease_token = :lease_token_match AND lease_expires_at > :now',
                $this->table,
                $changes,
            ),
            $parameters,
        ));
        if ($affected !== 1) {
            throw new InvalidJobTransition('Job lease is not active.');
        }
    }

    private function leased(string $id, string $leaseToken): JobRecord
    {
        $record = $this->get($id) ?? throw new JobNotFound('Job does not exist.');
        if ($record->status !== JobStatus::Running
            || $record->leaseToken === null
            || !hash_equals($record->leaseToken, $leaseToken)
            || $record->leaseExpiresAt === null
            || $record->leaseExpiresAt <= $this->clock->now()) {
            throw new InvalidJobTransition('Job lease is not active.');
        }
        return $record;
    }

    private function findIdempotent(JobDefinition $definition): ?JobRecord
    {
        $row = $this->database->fetchOne(new SqlStatement(
            sprintf('SELECT * FROM %s WHERE package_id = :package_id AND name = :name AND idempotency_key = :idempotency_key', $this->table),
            ['package_id' => $definition->packageId, 'name' => $definition->name, 'idempotency_key' => $definition->idempotencyKey],
        ));
        return $row === null ? null : $this->hydrate($row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): JobRecord
    {
        try {
            $payload = json_decode($this->requiredString($row, 'payload'), true, 9, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Stored job payload is invalid.', previous: $exception);
        }
        if (!is_array($payload)) {
            throw new \RuntimeException('Stored job payload must be an object.');
        }
        $normalizedPayload = [];
        foreach ($payload as $key => $value) {
            if (!is_string($key)) {
                throw new \RuntimeException('Stored job payload keys must be strings.');
            }
            $normalizedPayload[$key] = $value;
        }

        return new JobRecord(
            $this->requiredString($row, 'id'),
            new JobDefinition(
                $this->requiredString($row, 'package_id'),
                $this->requiredString($row, 'name'),
                $normalizedPayload,
                $this->nullableString($row, 'idempotency_key'),
            ),
            JobStatus::from($this->requiredString($row, 'status')),
            $this->requiredInt($row, 'progress_percent'),
            $this->nullableString($row, 'error_code'),
            $this->requiredInt($row, 'attempts'),
            $this->nullableString($row, 'lease_token'),
            ($leaseExpiresAt = $this->nullableString($row, 'lease_expires_at')) === null
                ? null
                : new DateTimeImmutable($leaseExpiresAt),
        );
    }

    /** @param array<string, mixed> $row */
    private function requiredString(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) && !is_int($value)) {
            throw new \RuntimeException(sprintf('Stored job field %s must be a string.', $key));
        }
        return (string) $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableString(array $row, string $key): ?string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }
        return $this->requiredString($row, $key);
    }

    /** @param array<string, mixed> $row */
    private function requiredInt(array $row, string $key): int
    {
        $value = $row[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && preg_match('/^-?[0-9]+$/D', $value) === 1) {
            return (int) $value;
        }
        throw new \RuntimeException(sprintf('Stored job field %s must be an integer.', $key));
    }

    private function timestamp(DateTimeImmutable $time): string
    {
        return $time->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
    }
}
