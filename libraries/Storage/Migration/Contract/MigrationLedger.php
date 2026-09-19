<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRecord;

interface MigrationLedger
{
    public function initialize(): void;

    /** @return list<MigrationRecord> */
    public function records(string $ownerId): array;

    public function nextBatch(): int;

    public function append(MigrationRecord $record): void;
}
