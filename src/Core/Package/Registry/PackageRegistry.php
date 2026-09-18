<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\LifecycleTransition;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;

interface PackageRegistry
{
    public function register(RegisteredPackageRelease $release): void;

    public function find(string $packageId, string $version): ?RegisteredPackageRelease;

    /** @return list<RegisteredPackageRelease> */
    public function releases(string $packageId): array;

    public function active(string $packageId): ?RegisteredPackageRelease;

    public function transition(
        string $packageId,
        string $version,
        PackageLifecycleState $target,
    ): LifecycleTransition;

    public function activate(string $packageId, string $version): LifecycleTransition;
}
