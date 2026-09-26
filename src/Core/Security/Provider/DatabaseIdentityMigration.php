<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabaseIdentityMigration
{
    public const OWNER_ID = 'core.security';

    public static function definition(): MigrationDefinition
    {
        $namespace = new StorageNamespace(self::OWNER_ID);
        $users = $namespace->table('users')->value;
        $identities = $namespace->table('identities')->value;
        $roles = $namespace->table('roles')->value;
        $userRoles = $namespace->table('user_roles')->value;
        $permissions = $namespace->table('permissions')->value;
        $rolePermissions = $namespace->table('role_permissions')->value;
        $bootstrap = $namespace->table('bootstrap')->value;

        return new MigrationDefinition(
            self::OWNER_ID,
            '001-create-identity-store',
            new MigrationMetadata(null, '1', 'Create external identities, local accounts and bootstrap roles'),
            [
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (id CHAR(32) PRIMARY KEY, display_name VARCHAR(120) NOT NULL, '
                    . 'email VARCHAR(254) NULL, avatar_url VARCHAR(2048) NULL, status VARCHAR(16) NOT NULL, '
                    . 'created_at VARCHAR(35) NOT NULL, last_login_at VARCHAR(35) NULL)',
                    $users,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (provider VARCHAR(32) NOT NULL, provider_subject VARCHAR(255) NOT NULL, '
                    . 'user_id CHAR(32) NOT NULL, provider_login VARCHAR(191) NOT NULL, provider_email VARCHAR(254) NULL, '
                    . 'email_verified INTEGER NOT NULL, linked_at VARCHAR(35) NOT NULL, last_used_at VARCHAR(35) NULL, '
                    . 'PRIMARY KEY (provider, provider_subject), FOREIGN KEY (user_id) REFERENCES %s(id) ON DELETE CASCADE)',
                    $identities,
                    $users,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (name VARCHAR(64) PRIMARY KEY, label VARCHAR(120) NOT NULL, is_system INTEGER NOT NULL)',
                    $roles,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (name VARCHAR(120) PRIMARY KEY, label VARCHAR(160) NOT NULL)',
                    $permissions,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (user_id CHAR(32) NOT NULL, role_name VARCHAR(64) NOT NULL, '
                    . 'PRIMARY KEY (user_id, role_name), FOREIGN KEY (user_id) REFERENCES %s(id) ON DELETE CASCADE, '
                    . 'FOREIGN KEY (role_name) REFERENCES %s(name) ON DELETE CASCADE)',
                    $userRoles,
                    $users,
                    $roles,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (role_name VARCHAR(64) NOT NULL, permission_name VARCHAR(120) NOT NULL, '
                    . 'PRIMARY KEY (role_name, permission_name), FOREIGN KEY (role_name) REFERENCES %s(name) ON DELETE CASCADE, '
                    . 'FOREIGN KEY (permission_name) REFERENCES %s(name) ON DELETE CASCADE)',
                    $rolePermissions,
                    $roles,
                    $permissions,
                )),
                new SqlStatement(sprintf(
                    'CREATE TABLE %s (singleton_id INTEGER PRIMARY KEY, owner_user_id CHAR(32) NULL, '
                    . 'FOREIGN KEY (owner_user_id) REFERENCES %s(id))',
                    $bootstrap,
                    $users,
                )),
                new SqlStatement(sprintf(
                    "INSERT INTO %s (name, label, is_system) VALUES ('owner', 'Owner', 1)",
                    $roles,
                )),
                new SqlStatement(sprintf(
                    "INSERT INTO %s (name, label, is_system) VALUES ('administrator', 'Administrator', 1)",
                    $roles,
                )),
                new SqlStatement(sprintf(
                    "INSERT INTO %s (name, label, is_system) VALUES ('user', 'User', 1)",
                    $roles,
                )),
                new SqlStatement(sprintf(
                    "INSERT INTO %s (name, label) VALUES "
                    . "('*', 'Full owner access'), ('admin.access', 'Administration panel access'), "
                    . "('users.view', 'View users'), ('users.manage', 'Manage users'), "
                    . "('roles.view', 'View roles'), ('roles.manage', 'Manage roles'), "
                    . "('logs.view', 'View audit logs'), ('settings.manage', 'Manage settings')",
                    $permissions,
                )),
                new SqlStatement(sprintf(
                    "INSERT INTO %s (role_name, permission_name) VALUES ('owner', '*')",
                    $rolePermissions,
                )),
                new SqlStatement(sprintf(
                    "INSERT INTO %s (role_name, permission_name) VALUES "
                    . "('administrator', 'admin.access'), ('administrator', 'users.view'), "
                    . "('administrator', 'users.manage'), ('administrator', 'roles.view'), "
                    . "('administrator', 'roles.manage'), ('administrator', 'logs.view'), "
                    . "('administrator', 'settings.manage')",
                    $rolePermissions,
                )),
                new SqlStatement(sprintf('INSERT INTO %s (singleton_id, owner_user_id) VALUES (1, NULL)', $bootstrap)),
            ],
            [
                new SqlStatement(sprintf('DROP TABLE %s', $bootstrap)),
                new SqlStatement(sprintf('DROP TABLE %s', $rolePermissions)),
                new SqlStatement(sprintf('DROP TABLE %s', $userRoles)),
                new SqlStatement(sprintf('DROP TABLE %s', $identities)),
                new SqlStatement(sprintf('DROP TABLE %s', $permissions)),
                new SqlStatement(sprintf('DROP TABLE %s', $roles)),
                new SqlStatement(sprintf('DROP TABLE %s', $users)),
            ],
        );
    }
}
