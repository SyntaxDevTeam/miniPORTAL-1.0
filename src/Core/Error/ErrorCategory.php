<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Error;

enum ErrorCategory: string
{
    case Validation = 'validation';
    case Dependency = 'dependency';
    case PermissionDenied = 'permission_denied';
    case NotFound = 'not_found';
    case Conflict = 'conflict';
    case ProviderUnavailable = 'provider_unavailable';
    case ModuleUnavailable = 'module_unavailable';
    case MigrationBlocked = 'migration_blocked';
    case CoreFailure = 'core_failure';
}
