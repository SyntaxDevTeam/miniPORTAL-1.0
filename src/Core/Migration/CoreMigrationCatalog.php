<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Migration;

use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\DatabaseAuditSinkMigration;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\DatabaseJobQueueMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistryMigration;

final class CoreMigrationCatalog
{
    /** @return array<string, list<MigrationDefinition>> */
    public function byOwner(): array
    {
        return [
            DatabasePackageRegistryMigration::OWNER_ID => [DatabasePackageRegistryMigration::definition()],
            DatabaseIdentityMigration::OWNER_ID => [DatabaseIdentityMigration::definition()],
            DatabaseJobQueueMigration::OWNER_ID => [DatabaseJobQueueMigration::definition()],
            DatabaseAuditSinkMigration::OWNER_ID => [DatabaseAuditSinkMigration::definition()],
        ];
    }
}
