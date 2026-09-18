<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

final readonly class PackagePreflightReport
{
    /** @param list<PreflightCheckResult> $checks */
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

    public function hasWarnings(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->status === PreflightCheckStatus::Warning) {
                return true;
            }
        }

        return false;
    }
}
