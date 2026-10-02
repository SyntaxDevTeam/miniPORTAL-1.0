<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabaseServiceApiMigration
{
    public const OWNER_ID = 'core.api';

    public static function definition(): MigrationDefinition
    {
        $scope = new StorageNamespace(self::OWNER_ID);
        $tokens = $scope->table('tokens')->value;
        $limits = $scope->table('rate_limits')->value;
        return new MigrationDefinition(self::OWNER_ID, '001-create-service-api-store',
            new MigrationMetadata(null, '1', 'Store hashed service tokens and request counters'),
            [
                new SqlStatement(sprintf('CREATE TABLE %s (id CHAR(32) PRIMARY KEY, secret_hash CHAR(64) NOT NULL, '
                    . 'scopes_json TEXT NOT NULL, expires_at VARCHAR(35) NOT NULL, revoked INTEGER NOT NULL)', $tokens)),
                new SqlStatement(sprintf('CREATE TABLE %s (token_id CHAR(32) NOT NULL, window_start INTEGER NOT NULL, '
                    . 'used INTEGER NOT NULL, PRIMARY KEY (token_id, window_start), '
                    . 'FOREIGN KEY (token_id) REFERENCES %s(id) ON DELETE CASCADE)', $limits, $tokens)),
            ],
            [new SqlStatement(sprintf('DROP TABLE %s', $limits)), new SqlStatement(sprintf('DROP TABLE %s', $tokens))]);
    }
}
