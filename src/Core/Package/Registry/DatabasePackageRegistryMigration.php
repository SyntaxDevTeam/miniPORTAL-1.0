<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabasePackageRegistryMigration
{
    public const OWNER_ID = 'core.packages';

    public static function definition(): MigrationDefinition
    {
        $scope = new StorageNamespace(self::OWNER_ID);
        $releases = $scope->table('releases')->value;
        $active = $scope->table('active')->value;

        return new MigrationDefinition(
            self::OWNER_ID,
            '001-create-package-registry',
            new MigrationMetadata(null, '1', 'Persist package releases and the active release pointer'),
            [
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (package_id VARCHAR(190) NOT NULL, version VARCHAR(64) NOT NULL, '
                    . 'manifest_json TEXT NOT NULL, release_path VARCHAR(2048) NOT NULL, state VARCHAR(32) NOT NULL, '
                    . 'PRIMARY KEY (package_id, version))',
                    $releases,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (package_id VARCHAR(190) PRIMARY KEY, version VARCHAR(64) NOT NULL, '
                    . 'FOREIGN KEY (package_id, version) REFERENCES %s(package_id, version))',
                    $active,
                    $releases,
                )),
            ],
            [new SqlStatement(sprintf('DROP TABLE %s', $active)), new SqlStatement(sprintf('DROP TABLE %s', $releases))],
        );
    }
}
