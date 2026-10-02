<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

/** Commits a release state and its active pointer in one storage transaction. */
interface AtomicPackageRegistry extends PackageRegistry
{
    public function commitTransition(
        PackageRelease $before,
        PackageRelease $after,
        bool $makeActive,
        bool $clearActive,
    ): void;
}
