<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;

final class InMemoryPackageRegistry implements PackageRegistry
{
    /** @var array<string, array<string, PackageRelease>> */
    private array $releases = [];

    /** @var array<string, string> */
    private array $activeVersions = [];

    public function add(PackageRelease $release): void
    {
        $id = $release->manifest->id;
        $version = $release->manifest->version;

        if (isset($this->releases[$id][$version])) {
            throw new \LogicException(sprintf('Package release %s@%s is already registered.', $id, $version));
        }

        $this->releases[$id][$version] = $release;
    }

    public function save(PackageRelease $release): void
    {
        $id = $release->manifest->id;
        $version = $release->manifest->version;

        if (!isset($this->releases[$id][$version])) {
            throw new \LogicException(sprintf('Package release %s@%s is not registered.', $id, $version));
        }

        $this->releases[$id][$version] = $release;
    }

    public function find(string $packageId, string $version): ?PackageRelease
    {
        return $this->releases[$packageId][$version] ?? null;
    }

    public function releases(string $packageId): array
    {
        $releases = array_values($this->releases[$packageId] ?? []);
        usort(
            $releases,
            static fn (PackageRelease $left, PackageRelease $right): int =>
                version_compare($left->manifest->version, $right->manifest->version),
        );

        return $releases;
    }

    public function active(string $packageId): ?PackageRelease
    {
        $version = $this->activeVersions[$packageId] ?? null;
        return $version === null ? null : $this->find($packageId, $version);
    }

    public function setActive(string $packageId, string $version): void
    {
        $release = $this->find($packageId, $version);
        if ($release === null) {
            throw new \LogicException(sprintf('Package release %s@%s is not registered.', $packageId, $version));
        }

        if ($release->state !== PackageState::Active) {
            throw new \LogicException(sprintf(
                'Package release %s@%s must be in active state before becoming the active registry pointer.',
                $packageId,
                $version,
            ));
        }

        $this->activeVersions[$packageId] = $version;
    }

    public function clearActive(string $packageId): void
    {
        unset($this->activeVersions[$packageId]);
    }
}
