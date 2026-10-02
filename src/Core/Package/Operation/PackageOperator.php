<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Operation;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;

/** Plan-first operations on deployed package metadata; uninstall preserves data and files. */
final readonly class PackageOperator
{
    public function __construct(
        private PackageRegistry $registry,
        private PackageLifecycleManager $lifecycle,
        private RequiredPackagePolicy $required,
    ) {
    }

    public function plan(string $operation, string $packageId, string $version = ''): PackageOperationPlan
    {
        if (!in_array($operation, ['disable', 'uninstall', 'rollback'], true)
            || preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1) {
            throw new \InvalidArgumentException('Package operation or ID is invalid.');
        }
        $active = $this->registry->active($packageId);
        $targetVersion = $operation === 'disable' ? ($active?->manifest->version ?? '') : $version;
        $release = $targetVersion === '' ? null : $this->registry->find($packageId, $targetVersion);
        $blockers = [];
        if ($release === null) {
            $blockers[] = 'Target release is not registered or active.';
        }
        if ($operation === 'disable') {
            if ($this->required->isRequired($packageId)) {
                $blockers[] = 'Required system package cannot be disabled.';
            }
            foreach ($this->activeDependents($packageId) as $dependent) {
                $blockers[] = sprintf('Active module %s depends on this package.', $dependent);
            }
        } elseif ($operation === 'uninstall') {
            if ($this->required->isRequired($packageId)) {
                $blockers[] = 'Required system package cannot be uninstalled.';
            }
            if ($active?->manifest->version === $targetVersion && $targetVersion !== '') {
                $blockers[] = 'Active release must be disabled before uninstall.';
            }
        } elseif ($operation === 'rollback') {
            if ($active === null || $active->manifest->version === $targetVersion
                || $release?->state !== PackageState::Active) {
                $blockers[] = 'Rollback target must be a retained, previously active release.';
            }
            if ($release !== null) {
                try {
                    $this->lifecycle->assertActiveDependencyCompatibility($release);
                } catch (\DomainException $exception) {
                    $blockers[] = $exception->getMessage();
                }
            }
        }
        $checksum = $this->checksum($operation, $packageId, $targetVersion);
        return new PackageOperationPlan($operation, $packageId, $targetVersion, $checksum, $blockers);
    }

    public function apply(string $operation, string $packageId, string $version, string $expectedChecksum): void
    {
        $plan = $this->plan($operation, $packageId, $version);
        if (!hash_equals($plan->checksum, $expectedChecksum)) {
            throw new \LogicException('Package state differs from the reviewed plan.');
        }
        if (!$plan->executable()) {
            throw new \DomainException(implode(' ', $plan->blockers));
        }
        match ($operation) {
            'disable' => $this->lifecycle->transition($packageId, $plan->version, PackageState::Disabled),
            'uninstall' => $this->lifecycle->uninstall($packageId, $plan->version),
            'rollback' => $this->lifecycle->rollback($packageId, $plan->version),
            default => throw new \LogicException('Unsupported package operation.'),
        };
    }

    /** @return list<string> */
    private function activeDependents(string $packageId): array
    {
        $dependents = [];
        foreach ($this->registry->allReleases() as $candidate) {
            if ($candidate->manifest->id !== $packageId
                && $this->registry->active($candidate->manifest->id)?->manifest->version === $candidate->manifest->version
                && isset($candidate->manifest->requiredModules[$packageId])) {
                $dependents[] = $candidate->manifest->id;
            }
        }
        return array_values(array_unique($dependents));
    }

    private function checksum(string $operation, string $packageId, string $version): string
    {
        $snapshot = [];
        foreach ($this->registry->allReleases() as $release) {
            $snapshot[] = [
                $release->manifest->id,
                $release->manifest->version,
                $release->manifest->requiredModules,
                $release->state->value,
                $this->registry->active($release->manifest->id)?->manifest->version,
            ];
        }
        return hash('sha256', json_encode([$operation, $packageId, $version, $snapshot], JSON_THROW_ON_ERROR));
    }
}
