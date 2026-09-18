<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

final class PackageLifecycle
{
    public function canTransition(PackageState $from, PackageState $to): bool
    {
        return in_array($to, $this->allowedTargets($from), true);
    }

    public function assertTransition(PackageState $from, PackageState $to): void
    {
        if (!$this->canTransition($from, $to)) {
            throw InvalidPackageTransition::between($from, $to);
        }
    }

    /** @return list<PackageState> */
    public function allowedTargets(PackageState $from): array
    {
        return match ($from) {
            PackageState::Discovered => [
                PackageState::Validated,
                PackageState::Failed,
            ],
            PackageState::Validated => [
                PackageState::Staged,
                PackageState::Incompatible,
                PackageState::Failed,
            ],
            PackageState::Staged => [
                PackageState::PreflightPassed,
                PackageState::FailedPreflight,
                PackageState::Incompatible,
                PackageState::Failed,
            ],
            PackageState::PreflightPassed => [
                PackageState::Ready,
                PackageState::MigrationBlocked,
                PackageState::Failed,
            ],
            PackageState::Ready => [
                PackageState::Active,
                PackageState::Disabled,
                PackageState::Failed,
            ],
            PackageState::Active => [
                PackageState::Degraded,
                PackageState::Disabled,
                PackageState::Failed,
            ],
            PackageState::Degraded => [
                PackageState::Active,
                PackageState::Disabled,
                PackageState::Failed,
            ],
            PackageState::Disabled => [
                PackageState::Ready,
                PackageState::Failed,
            ],
            PackageState::FailedPreflight => [
                PackageState::Staged,
                PackageState::Disabled,
            ],
            PackageState::Incompatible => [
                PackageState::Staged,
                PackageState::Disabled,
            ],
            PackageState::MigrationBlocked => [
                PackageState::PreflightPassed,
                PackageState::Disabled,
            ],
            PackageState::Failed => [
                PackageState::Disabled,
            ],
        };
    }
}
