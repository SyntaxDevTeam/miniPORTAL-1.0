<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Model;

final readonly class JobRecord
{
    public function __construct(
        public string $id,
        public JobDefinition $definition,
        public JobStatus $status,
        public int $progressPercent = 0,
        public ?string $errorCode = null,
    ) {
    }
}
