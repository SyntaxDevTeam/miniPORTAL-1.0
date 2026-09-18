<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

final class PackageLifecycle
{
    /** @var array<string, list<PackageLifecycleState>> */
    private const TRANSITIONS = [
        'discovered' => [PackageLifecycleState::Validated, PackageLifecycleState::Failed],
        'validated' => [PackageLifecycleState::Staged, PackageLifecycleState::Failed],
        'staged' => [PackageLifecycleState::PreflightPassed, PackageLifecycleState::Failed],
        'preflight_passed' => [PackageLifecycleState::Ready, PackageLifecycleState::Failed],
        'ready' => [PackageLifecycleState::Active, PackageLifecycleState::Disabled, PackageLifecycleState::Failed],
        'active' => [PackageLifecycleState::Degraded, PackageLifecycleState::Disabled, PackageLifecycleState::Failed],
        'degraded' => [PackageLifecycleState::Active, PackageLifecycleState::Disabled, PackageLifecycleState::Failed],
        'disabled' => [PackageLifecycleState::Ready, PackageLifecycleState::Failed],
        'failed' => [PackageLifecycleState::Staged, PackageLifecycleState::Disabled],
    ];

    public function transition(PackageLifecycleState $from, PackageLifecycleState $to): LifecycleTransition
    {
        if ($from === $to) {
            return LifecycleTransition::rejected($from, $to, 'Lifecycle transition must change state.');
        }

        $allowed = self::TRANSITIONS[$from->value] ?? [];
        if (!in_array($to, $allowed, true)) {
            return LifecycleTransition::rejected(
                $from,
                $to,
                sprintf('Transition %s -> %s is not allowed.', $from->value, $to->value),
            );
        }

        return LifecycleTransition::allowed($from, $to);
    }

    /** @return list<PackageLifecycleState> */
    public function allowedTargets(PackageLifecycleState $from): array
    {
        return self::TRANSITIONS[$from->value] ?? [];
    }
}
