<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Migration\Legacy;

final readonly class IdentityImportPlan
{
    public function __construct(
        public string $checksum,
        public int $users,
        public int $identities,
        public int $roles,
        public int $permissions,
        public int $userRoles,
        public int $rolePermissions,
    ) {
    }
}
