<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\LifecycleTransition;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;

final class InMemoryPackageRegistry implements PackageRegistry
{
    /** @var array<string, array<string, RegisteredPackageRelease>> */
    private array $releases = [];

    /** @var array<string, string> */
    private array $activeVersions = [];

    public function __construct(private readonly PackageLifecycle $lifecycle)
    {
    }

    public function register(RegisteredPackageRelease $release): void
    {
        if (isset($this->releases[$release->packageId][$release->version])) {
            throw new \LogicException(sprintf(
                'Package release %s@%s is already registered.',
                $release->packageId,
                $release->version,
            ));
        }

        $this->releases[$release->packageId][$release->version] = $release;
    }

    public function find(string $packageId, string $version): ?RegisteredPackageRelease
    {
        return $this->releases[$packageId][$version] ?? null;
    }

    public function releases(string $packageId): array
    {
        $releases = array_values($this->releases[$packageId] ?? []);
        usort(
            $releases,
            static fn (RegisteredPackageRelease $left, RegisteredPackageRelease $right): int
                => version_compare($left->version, $right->version),
        );

        return $releases;
    }

    public function active(string $packageId): ?RegisteredPackageRelease
    {
        $version = $this->activeVersions[$packageId] ?? null;
        return $version === null ? null : $this->find($packageId, $version);
    }

    public function transition(
        string $packageId,
        string $version,
        PackageLifecycleState $target,
    ): LifecycleTransition {
        $release = $this->find($packageId, $version);
        if ($release === null) {
            return LifecycleTransition::rejected(
                PackageLifecycleState::Failed,
                $target,
                sprintf('Package release %s@%s is not registered.', $packageId, $version),
            );
        }

        $transition = $this->lifecycle->transition($release->state, $target);
        if (!$transition->allowed) {
            return $transition;
        }

        $this->releases[$packageId][$version] = $release->withState($target);

        if ($target === PackageLifecycleState::Disabled && ($this->activeVersions[$packageId] ?? null) === $version) {
            unset($this->activeVersions[$packageId]);
        }

        return $transition;
    }

    public function activate(string $packageId, string $version): LifecycleTransition
    {
        $target = $this->find($packageId, $version);
        if ($target === null) {
            return LifecycleTransition::rejected(
                PackageLifecycleState::Failed,
                PackageLifecycleState::Active,
                sprintf('Package release %s@%s is not registered.', $packageId, $version),
            );
        }

        $transition = $this->lifecycle->transition($target->state, PackageLifecycleState::Active);
        if (!$transition->allowed) {
            return $transition;
        }

        $current = $this->active($packageId);
        if ($current !== null && $current->version !== $version) {
            $disable = $this->lifecycle->transition($current->state, PackageLifecycleState::Disabled);
            if (!$disable->allowed) {
                return LifecycleTransition::rejected(
                    $target->state,
                    PackageLifecycleState::Active,
                    sprintf('Current release %s cannot be disabled atomically.', $current->version),
                );
            }

            $this->releases[$packageId][$current->version] = $current->withState(PackageLifecycleState::Disabled);
        }

        $this->releases[$packageId][$version] = $target->withState(PackageLifecycleState::Active);
        $this->activeVersions[$packageId] = $version;

        return $transition;
    }
}
