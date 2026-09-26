<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class DatabaseIdentityAccountRepository implements IdentityAccountRepository
{
    private string $users;
    private string $identities;
    private string $userRoles;
    private string $rolePermissions;
    private string $bootstrap;

    public function __construct(private Database $database, private Clock $clock)
    {
        $namespace = new StorageNamespace(DatabaseIdentityMigration::OWNER_ID);
        $this->users = $namespace->table('users')->value;
        $this->identities = $namespace->table('identities')->value;
        $this->userRoles = $namespace->table('user_roles')->value;
        $this->rolePermissions = $namespace->table('role_permissions')->value;
        $this->bootstrap = $namespace->table('bootstrap')->value;
    }

    public function resolve(ExternalIdentity $identity): UserAccount
    {
        return $this->database->transaction(function (Database $database) use ($identity): UserAccount {
            // A portable no-op update serializes all first-owner decisions on one row.
            $database->execute(new SqlStatement(sprintf(
                'UPDATE %s SET singleton_id = singleton_id WHERE singleton_id = 1',
                $this->bootstrap,
            )));

            $existing = $this->findByIdentity($database, $identity->provider, $identity->subject);
            if ($existing !== null) {
                $this->touch($database, $existing->id, $identity);
                return $this->findById($database, $existing->id)
                    ?? throw new \RuntimeException('Resolved account disappeared during authentication.');
            }

            $bootstrap = $database->fetchOne(new SqlStatement(sprintf(
                'SELECT owner_user_id FROM %s WHERE singleton_id = 1',
                $this->bootstrap,
            ))) ?? throw new \RuntimeException('Identity bootstrap row is unavailable.');
            $isFirstOwner = ($bootstrap['owner_user_id'] ?? null) === null;
            $accountId = bin2hex(random_bytes(16));
            $now = $this->timestamp();
            $status = $isFirstOwner ? AccountStatus::Active : AccountStatus::Pending;
            $role = $isFirstOwner ? 'owner' : 'user';

            $database->execute(new SqlStatement(sprintf(
                'INSERT INTO %s (id, display_name, email, avatar_url, status, created_at, last_login_at) '
                . 'VALUES (:id, :display_name, :email, :avatar_url, :status, :created_at, :last_login_at)',
                $this->users,
            ), [
                'id' => $accountId,
                'display_name' => $identity->displayName,
                'email' => $identity->emailVerified ? $identity->email : null,
                'avatar_url' => $identity->avatarUrl,
                'status' => $status->value,
                'created_at' => $now,
                'last_login_at' => $now,
            ]));
            $database->execute(new SqlStatement(sprintf(
                'INSERT INTO %s (provider, provider_subject, user_id, provider_login, provider_email, '
                . 'email_verified, linked_at, last_used_at) VALUES (:provider, :subject, :user_id, '
                . ':login, :email, :verified, :linked_at, :last_used_at)',
                $this->identities,
            ), [
                'provider' => $identity->provider,
                'subject' => $identity->subject,
                'user_id' => $accountId,
                'login' => $identity->displayName,
                'email' => $identity->email,
                'verified' => $identity->emailVerified,
                'linked_at' => $now,
                'last_used_at' => $now,
            ]));
            $database->execute(new SqlStatement(sprintf(
                'INSERT INTO %s (user_id, role_name) VALUES (:user_id, :role_name)',
                $this->userRoles,
            ), ['user_id' => $accountId, 'role_name' => $role]));

            if ($isFirstOwner) {
                $claimed = $database->execute(new SqlStatement(sprintf(
                    'UPDATE %s SET owner_user_id = :user_id WHERE singleton_id = 1 AND owner_user_id IS NULL',
                    $this->bootstrap,
                ), ['user_id' => $accountId]));
                if ($claimed !== 1) {
                    throw new \RuntimeException('First Owner bootstrap lost its atomic claim.');
                }
            }

            return new UserAccount(
                $accountId,
                $identity->displayName,
                $identity->emailVerified ? $identity->email : null,
                $identity->avatarUrl,
                $status,
                [$role],
                $isFirstOwner ? ['*'] : [],
            );
        });
    }

    private function findByIdentity(Database $database, string $provider, string $subject): ?UserAccount
    {
        $row = $database->fetchOne(new SqlStatement(sprintf(
            'SELECT users.id, users.display_name, users.email, users.avatar_url, users.status FROM %s identities '
            . 'JOIN %s users ON users.id = identities.user_id '
            . 'WHERE identities.provider = :provider AND identities.provider_subject = :subject',
            $this->identities,
            $this->users,
        ), ['provider' => $provider, 'subject' => $subject]));

        return $row === null ? null : $this->hydrate($database, $row);
    }

    private function findById(Database $database, string $id): ?UserAccount
    {
        $row = $database->fetchOne(new SqlStatement(sprintf(
            'SELECT id, display_name, email, avatar_url, status FROM %s WHERE id = :id',
            $this->users,
        ), ['id' => $id]));

        return $row === null ? null : $this->hydrate($database, $row);
    }

    /** @param array<string, mixed> $row */
    private function hydrate(Database $database, array $row): UserAccount
    {
        $id = $this->string($row, 'id');
        $roles = [];
        foreach ($database->fetchAll(new SqlStatement(sprintf(
            'SELECT role_name FROM %s WHERE user_id = :user_id ORDER BY role_name',
            $this->userRoles,
        ), ['user_id' => $id])) as $role) {
            $roles[] = $this->string($role, 'role_name');
        }
        $permissions = [];
        foreach ($database->fetchAll(new SqlStatement(sprintf(
            'SELECT DISTINCT role_permissions.permission_name FROM %s role_permissions '
            . 'JOIN %s user_roles ON user_roles.role_name = role_permissions.role_name '
            . 'WHERE user_roles.user_id = :user_id ORDER BY role_permissions.permission_name',
            $this->rolePermissions,
            $this->userRoles,
        ), ['user_id' => $id])) as $permission) {
            $permissions[] = $this->string($permission, 'permission_name');
        }

        return new UserAccount(
            $id,
            $this->string($row, 'display_name'),
            $this->nullableString($row, 'email'),
            $this->nullableString($row, 'avatar_url'),
            AccountStatus::from($this->string($row, 'status')),
            $roles,
            $permissions,
        );
    }

    private function touch(Database $database, string $userId, ExternalIdentity $identity): void
    {
        $now = $this->timestamp();
        $database->execute(new SqlStatement(sprintf(
            'UPDATE %s SET provider_login = :login, provider_email = :email, email_verified = :verified, '
            . 'last_used_at = :last_used_at WHERE provider = :provider AND provider_subject = :subject',
            $this->identities,
        ), [
            'login' => $identity->displayName,
            'email' => $identity->email,
            'verified' => $identity->emailVerified,
            'last_used_at' => $now,
            'provider' => $identity->provider,
            'subject' => $identity->subject,
        ]));
        $database->execute(new SqlStatement(sprintf(
            'UPDATE %s SET last_login_at = :last_login_at WHERE id = :id',
            $this->users,
        ), ['last_login_at' => $now, 'id' => $userId]));
    }

    /** @param array<string, mixed> $row */
    private function string(array $row, string $key): string
    {
        $value = $row[$key] ?? null;
        if (!is_string($value) || $value === '') {
            throw new \RuntimeException(sprintf('Identity row field %s is invalid.', $key));
        }
        return $value;
    }

    /** @param array<string, mixed> $row */
    private function nullableString(array $row, string $key): ?string
    {
        $value = $row[$key] ?? null;
        if ($value !== null && !is_string($value)) {
            throw new \RuntimeException(sprintf('Identity row field %s is invalid.', $key));
        }
        return $value;
    }

    private function timestamp(): string
    {
        return $this->clock->now()->format('Y-m-d\TH:i:s.uP');
    }
}
