<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Operation;

use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlan;

final readonly class PackageInstallPlan
{
    /** @param list<string> $blockers */
    public function __construct(
        public PackageManifest $manifest,
        public string $sourcePath,
        public string $sourceChecksum,
        public string $checksum,
        public MigrationPlan $migrations,
        public array $blockers,
    ) {
    }

    public function executable(): bool
    {
        return $this->blockers === [];
    }
}
