<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

interface PreflightCheck
{
    public function id(): string;

    public function run(PackagePreflightContext $context): PreflightCheckResult;
}
