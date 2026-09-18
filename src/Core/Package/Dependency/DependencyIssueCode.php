<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Dependency;

enum DependencyIssueCode: string
{
    case DuplicatePackage = 'duplicate_package';
    case UnsupportedConstraint = 'unsupported_constraint';
    case CoreVersionMismatch = 'core_version_mismatch';
    case MissingModule = 'missing_module';
    case ModuleVersionMismatch = 'module_version_mismatch';
    case MissingCapability = 'missing_capability';
    case CapabilityVersionMismatch = 'capability_version_mismatch';
    case DependencyCycle = 'dependency_cycle';
}
