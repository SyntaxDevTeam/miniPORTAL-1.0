<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Migration;

use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\DatabaseAuditSinkMigration;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\DatabaseJobQueueMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistryMigration;
use SyntaxDevTeam\MiniPortal\Core\Widget\DatabaseWidgetMigration;
use SyntaxDevTeam\MiniPortal\Core\Api\DatabaseServiceApiMigration;

final class CoreMigrationCatalog
{
    /** @return array<string, list<MigrationDefinition>> */
    public function byOwner(): array
    {
        return [
            DatabasePackageRegistryMigration::OWNER_ID => [DatabasePackageRegistryMigration::definition()],
            DatabaseWidgetMigration::OWNER_ID => [DatabaseWidgetMigration::definition()],
            DatabaseServiceApiMigration::OWNER_ID => [DatabaseServiceApiMigration::definition()],
            DatabaseIdentityMigration::OWNER_ID => [DatabaseIdentityMigration::definition()],
            DatabaseJobQueueMigration::OWNER_ID => [DatabaseJobQueueMigration::definition()],
            DatabaseAuditSinkMigration::OWNER_ID => [DatabaseAuditSinkMigration::definition()],
        ];
    }
}
