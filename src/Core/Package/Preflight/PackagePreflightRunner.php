<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

final readonly class PackagePreflightRunner
{
    /** @param list<PreflightCheck> $checks */
    public function __construct(private array $checks)
    {
    }

    public function run(PackagePreflightContext $context): PackagePreflightReport
    {
        /** @var list<PreflightCheckResult> $results */
        $results = [];

        foreach ($this->checks as $check) {
            try {
                $results[] = $check->run($context);
            } catch (\Throwable $throwable) {
                $results[] = PreflightCheckResult::failed(
                    $check->id(),
                    sprintf('Preflight check crashed: %s', $throwable->getMessage()),
                );
            }
        }

        return new PackagePreflightReport(
            $context->release->manifest->id,
            $context->release->manifest->version,
            $results,
        );
    }
}
