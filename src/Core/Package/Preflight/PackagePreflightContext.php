<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolution;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final readonly class PackagePreflightContext
{
    public function __construct(
        public PackageRelease $release,
        public DependencyResolution $dependencyResolution,
    ) {
    }
}
