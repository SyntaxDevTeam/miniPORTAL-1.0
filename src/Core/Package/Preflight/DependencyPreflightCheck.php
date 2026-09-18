<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

final class DependencyPreflightCheck implements PreflightCheck
{
    public function id(): string
    {
        return 'dependencies';
    }

    public function run(PackagePreflightContext $context): PreflightCheckResult
    {
        if ($context->dependencyResolution->isSuccessful()) {
            return PreflightCheckResult::passed(
                $this->id(),
                'All declared package dependencies and capabilities are satisfied.',
            );
        }

        return PreflightCheckResult::failed(
            $this->id(),
            sprintf(
                'Dependency resolution reported %d blocking issue(s).',
                count($context->dependencyResolution->issues),
            ),
        );
    }
}
