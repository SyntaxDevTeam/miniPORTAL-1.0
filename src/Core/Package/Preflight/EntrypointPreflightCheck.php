<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;

final class EntrypointPreflightCheck implements PreflightCheck
{
    public function id(): string
    {
        return 'entrypoint';
    }

    public function run(PackagePreflightContext $context): PreflightCheckResult
    {
        $manifest = $context->release->manifest;
        $requiresEntrypoint = $manifest->type === PackageType::Module
            || $manifest->type === PackageType::Provider;

        if (!$requiresEntrypoint) {
            return PreflightCheckResult::passed($this->id(), 'Package type does not require an entrypoint.');
        }

        if ($manifest->entrypoint === null || trim($manifest->entrypoint) === '') {
            return PreflightCheckResult::failed($this->id(), 'Package entrypoint is missing.');
        }

        return PreflightCheckResult::passed($this->id(), 'Package entrypoint declaration is present.');
    }
}
