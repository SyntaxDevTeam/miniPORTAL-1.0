<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Model;

use DateTimeImmutable;

final readonly class JobRecord
{
    public function __construct(
        public string $id,
        public JobDefinition $definition,
        public JobStatus $status,
        public int $progressPercent = 0,
        public ?string $errorCode = null,
        public int $attempts = 0,
        public ?string $leaseToken = null,
        public ?DateTimeImmutable $leaseExpiresAt = null,
    ) {
    }
}
