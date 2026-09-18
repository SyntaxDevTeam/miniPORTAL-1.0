<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

final readonly class PreflightReport
{
    /** @param list<PreflightCheck> $checks */
    public function __construct(
        public string $packageId,
        public string $version,
        public array $checks,
    ) {
    }

    public function passed(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === PreflightCheckStatus::Failed) {
                return false;
            }
        }

        return true;
    }
}
