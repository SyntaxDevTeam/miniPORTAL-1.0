<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Provider;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabaseAuditSinkMigration
{
    public const OWNER_ID = 'core.audit';

    public static function definition(): MigrationDefinition
    {
        $table = (new StorageNamespace(self::OWNER_ID))->table('events')->value;

        return new MigrationDefinition(
            self::OWNER_ID,
            '001-create-audit-events',
            new MigrationMetadata(null, '1', 'Create durable audit event store'),
            [new SqlStatement(sprintf(
                'CREATE TABLE %s ('
                . 'id CHAR(32) PRIMARY KEY, package_id VARCHAR(128) NOT NULL, actor VARCHAR(128) NOT NULL, '
                . 'action VARCHAR(128) NOT NULL, target VARCHAR(512) NOT NULL, result VARCHAR(16) NOT NULL, '
                . 'correlation_id VARCHAR(64) NOT NULL, occurred_at VARCHAR(35) NOT NULL, context_json TEXT NOT NULL)',
                $table,
            ))],
            [new SqlStatement(sprintf('DROP TABLE %s', $table))],
        );
    }
}
