<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final class MigrationFailed extends \RuntimeException
{
    public function __construct(
        public readonly string $ownerId,
        public readonly string $migrationId,
        \Throwable $previous,
    ) {
        parent::__construct(
            sprintf('Migration %s for package %s failed.', $migrationId, $ownerId),
            previous: $previous,
        );
    }
}
