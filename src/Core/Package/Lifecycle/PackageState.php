<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

enum PackageState: string
{
    case Discovered = 'discovered';
    case Validated = 'validated';
    case Staged = 'staged';
    case PreflightPassed = 'preflight_passed';
    case Ready = 'ready';
    case Active = 'active';
    case Degraded = 'degraded';
    case Disabled = 'disabled';
    case FailedPreflight = 'failed_preflight';
    case Incompatible = 'incompatible';
    case MigrationBlocked = 'migration_blocked';
    case Failed = 'failed';
}
