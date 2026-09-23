<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Provider;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabaseJobQueueMigration
{
    public const OWNER_ID = 'core.jobs';

    public static function definition(): MigrationDefinition
    {
        $table = (new StorageNamespace(self::OWNER_ID))->table('queue')->value;

        return new MigrationDefinition(
            self::OWNER_ID,
            '001-create-job-queue',
            new MigrationMetadata(null, '1', 'Create durable jobs queue'),
            [new SqlStatement(sprintf(
                'CREATE TABLE %s ('
                . 'id CHAR(32) PRIMARY KEY, package_id VARCHAR(128) NOT NULL, name VARCHAR(64) NOT NULL, '
                . 'payload TEXT NOT NULL, idempotency_key VARCHAR(128) NULL, status VARCHAR(16) NOT NULL, '
                . 'progress_percent INTEGER NOT NULL, error_code VARCHAR(64) NULL, attempts INTEGER NOT NULL, '
                . 'lease_token CHAR(32) NULL, lease_expires_at VARCHAR(35) NULL, '
                . 'queue_order INTEGER NOT NULL UNIQUE, created_at VARCHAR(35) NOT NULL, updated_at VARCHAR(35) NOT NULL, '
                . 'UNIQUE (package_id, name, idempotency_key))',
                $table,
            ))],
            [new SqlStatement(sprintf('DROP TABLE %s', $table))],
        );
    }
}
