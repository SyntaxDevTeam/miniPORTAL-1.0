<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabaseWidgetMigration
{
    public const OWNER_ID = 'core.widgets';

    public static function definition(): MigrationDefinition
    {
        $table = (new StorageNamespace(self::OWNER_ID))->table('placements')->value;
        return new MigrationDefinition(self::OWNER_ID, '001-create-widget-placements',
            new MigrationMetadata(null, '1', 'Store widget instances and named slot assignments'),
            [new SqlStatement(sprintf('CREATE TABLE %s (id VARCHAR(128) PRIMARY KEY, page_id VARCHAR(128) NOT NULL, '
                . 'slot_id VARCHAR(128) NOT NULL, module_id VARCHAR(190) NOT NULL, widget_type VARCHAR(128) NOT NULL, '
                . 'position INTEGER NOT NULL, config_json TEXT NOT NULL, required_permission VARCHAR(120) NULL)', $table)),
                new SqlStatement(sprintf('CREATE INDEX %s ON %s (page_id, slot_id, position)',
                    $table . '_slot', $table))],
            [new SqlStatement(sprintf('DROP TABLE %s', $table))]);
    }
}
