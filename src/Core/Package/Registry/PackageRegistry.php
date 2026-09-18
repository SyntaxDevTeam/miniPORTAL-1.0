<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

interface PackageRegistry
{
    public function add(PackageRelease $release): void;

    public function save(PackageRelease $release): void;

    public function find(string $packageId, string $version): ?PackageRelease;

    /** @return list<PackageRelease> */
    public function releases(string $packageId): array;

    public function active(string $packageId): ?PackageRelease;

    public function setActive(string $packageId, string $version): void;

    public function clearActive(string $packageId): void;
}
