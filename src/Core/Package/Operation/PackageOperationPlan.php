<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Operation;

final readonly class PackageOperationPlan
{
    /** @param list<string> $blockers */
    public function __construct(
        public string $operation,
        public string $packageId,
        public string $version,
        public string $checksum,
        public array $blockers,
    ) {
    }

    public function executable(): bool
    {
        return $this->blockers === [];
    }
}
