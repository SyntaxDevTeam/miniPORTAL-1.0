<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Migration\Legacy;

use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

/** One-time, plan-first import from the legacy miniPORTAL identity schema. */
final readonly class IdentityImporter
{
    private string $users;
    private string $identities;
    private string $roles;
    private string $permissions;
    private string $userRoles;
    private string $rolePermissions;
    private string $bootstrap;

    public function __construct(private Database $database)
    {
        $scope = new StorageNamespace(DatabaseIdentityMigration::OWNER_ID);
        $this->users = $scope->table('users')->value;
        $this->identities = $scope->table('identities')->value;
        $this->roles = $scope->table('roles')->value;
        $this->permissions = $scope->table('permissions')->value;
        $this->userRoles = $scope->table('user_roles')->value;
        $this->rolePermissions = $scope->table('role_permissions')->value;
        $this->bootstrap = $scope->table('bootstrap')->value;
    }

    public function plan(string $snapshot): IdentityImportPlan
    {
        $source = LegacyIdentitySnapshot::parse($snapshot);
        $this->assertEmptyTarget($this->database);
        return $this->describe($source);
    }

    public function apply(string $snapshot, string $expectedChecksum): IdentityImportPlan
    {
        if (preg_match('/^[a-f0-9]{64}$/D', $expectedChecksum) !== 1
            || !hash_equals($expectedChecksum, hash('sha256', $snapshot))) {
            throw new \LogicException('Legacy snapshot checksum differs from the reviewed plan.');
        }
        $source = LegacyIdentitySnapshot::parse($snapshot);
        $plan = $this->describe($source);

        $this->database->transaction(function (Database $db) use ($source): void {
            $this->assertEmptyTarget($db);
            foreach ($source->roles as $role) {
                if ($this->exists($db, $this->roles, 'name', $role['name'])) {
                    continue;
                }
                $db->execute(new SqlStatement(sprintf(
                    'INSERT INTO %s (name, label, is_system) VALUES (:name, :label, :system)',
                    $this->roles,
                ), ['name' => $role['name'], 'label' => $role['label'], 'system' => false]));
            }
            foreach ($source->permissions as $permission) {
                if ($this->exists($db, $this->permissions, 'name', $permission['name'])) {
                    continue;
                }
                $db->execute(new SqlStatement(sprintf(
                    'INSERT INTO %s (name, label) VALUES (:name, :label)',
                    $this->permissions,
                ), ['name' => $permission['name'], 'label' => $permission['label']]));
            }
            foreach ($source->users as $user) {
                $db->execute(new SqlStatement(sprintf(
                    'INSERT INTO %s (id, display_name, email, avatar_url, status, created_at, last_login_at) '
                    . 'VALUES (:id, :display_name, :email, :avatar_url, :status, :created_at, :last_login_at)',
                    $this->users,
                ), [
                    'id' => self::newId($user['id']),
                    'display_name' => $user['display_name'],
                    'email' => $user['email'],
                    'avatar_url' => $user['avatar_url'],
                    'status' => $user['status'],
                    'created_at' => $user['created_at'],
                    'last_login_at' => $user['last_login_at'],
                ]));
            }
            foreach ($source->identities as $identity) {
                $db->execute(new SqlStatement(sprintf(
                    'INSERT INTO %s (provider, provider_subject, user_id, provider_login, provider_email, '
                    . 'email_verified, linked_at, last_used_at) VALUES '
                    . '(:provider, :subject, :user_id, :login, :email, :verified, :linked_at, :last_used_at)',
                    $this->identities,
                ), [
                    'provider' => $identity['provider'],
                    'subject' => $identity['provider_subject'],
                    'user_id' => self::newId($identity['user_id']),
                    'login' => $identity['provider_login'] ?: $identity['provider_subject'],
                    'email' => $identity['provider_email'],
                    'verified' => (bool) $identity['email_verified'],
                    'linked_at' => $identity['linked_at'],
                    'last_used_at' => $identity['last_used_at'],
                ]));
            }
            foreach ($source->userRoles as $assignment) {
                $db->execute(new SqlStatement(sprintf(
                    'INSERT INTO %s (user_id, role_name) VALUES (:user_id, :role_name)',
                    $this->userRoles,
                ), ['user_id' => self::newId($assignment['user_id']), 'role_name' => $assignment['role_name']]));
            }
            foreach ($source->rolePermissions as $assignment) {
                $existing = $db->fetchOne(new SqlStatement(sprintf(
                    'SELECT permission_name FROM %s WHERE role_name = :role AND permission_name = :permission',
                    $this->rolePermissions,
                ), ['role' => $assignment['role_name'], 'permission' => $assignment['permission_name']]));
                if ($existing !== null) {
                    continue;
                }
                $db->execute(new SqlStatement(sprintf(
                    'INSERT INTO %s (role_name, permission_name) VALUES (:role, :permission)',
                    $this->rolePermissions,
                ), ['role' => $assignment['role_name'], 'permission' => $assignment['permission_name']]));
            }
            $ownerId = $source->ownerId;
            $changed = $db->execute(new SqlStatement(sprintf(
                'UPDATE %s SET owner_user_id = :owner WHERE singleton_id = 1 AND owner_user_id IS NULL',
                $this->bootstrap,
            ), ['owner' => self::newId($ownerId)]));
            if ($changed !== 1) {
                throw new \RuntimeException('Owner bootstrap row was unavailable or already claimed.');
            }
        });
        return $plan;
    }

    public static function newId(int|string $legacyId): string
    {
        return substr(hash('sha256', 'legacy:syntaxdevteam.pl:user:' . (string) $legacyId), 0, 32);
    }

    private function exists(Database $db, string $table, string $column, string $value): bool
    {
        return $db->fetchOne(new SqlStatement(sprintf(
            'SELECT %s FROM %s WHERE %s = :value', $column, $table, $column,
        ), ['value' => $value])) !== null;
    }

    private function assertEmptyTarget(Database $db): void
    {
        foreach ([$this->users, $this->identities, $this->userRoles] as $table) {
            $row = $db->fetchOne(new SqlStatement(sprintf('SELECT COUNT(*) AS total FROM %s', $table)));
            if ($row === null || !isset($row['total']) || !is_numeric($row['total']) || (int) $row['total'] !== 0) {
                throw new \LogicException('Identity target is not empty; import cannot overwrite existing accounts.');
            }
        }
        $bootstrap = $db->fetchOne(new SqlStatement(sprintf(
            'SELECT owner_user_id FROM %s WHERE singleton_id = 1', $this->bootstrap,
        )));
        if ($bootstrap === null || ($bootstrap['owner_user_id'] ?? null) !== null) {
            throw new \LogicException('Owner bootstrap is unavailable or already claimed.');
        }
    }

    private function describe(LegacyIdentitySnapshot $source): IdentityImportPlan
    {
        return new IdentityImportPlan(
            $source->checksum,
            count($source->users),
            count($source->identities),
            count($source->roles),
            count($source->permissions),
            count($source->userRoles),
            count($source->rolePermissions),
        );
    }
}
