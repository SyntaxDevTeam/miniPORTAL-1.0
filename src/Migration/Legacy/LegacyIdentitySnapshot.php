<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Migration\Legacy;

use DateTimeImmutable;
use DateTimeZone;
use JsonException;

/**
 * @phpstan-type UserRow array{id:string,display_name:string,email:?string,avatar_url:?string,status:string,created_at:string,last_login_at:?string}
 * @phpstan-type IdentityRow array{user_id:string,provider:string,provider_subject:string,provider_login:string,provider_email:?string,email_verified:bool,linked_at:string,last_used_at:?string}
 * @phpstan-type RoleRow array{name:string,label:string}
 * @phpstan-type PermissionRow array{name:string,label:string}
 * @phpstan-type UserRoleRow array{user_id:string,role_name:string}
 * @phpstan-type RolePermissionRow array{role_name:string,permission_name:string}
 */
final readonly class LegacyIdentitySnapshot
{
    /**
     * @param list<UserRow> $users
     * @param list<IdentityRow> $identities
     * @param list<RoleRow> $roles
     * @param list<PermissionRow> $permissions
     * @param list<UserRoleRow> $userRoles
     * @param list<RolePermissionRow> $rolePermissions
     */
    private function __construct(
        public string $checksum,
        public array $users,
        public array $identities,
        public array $roles,
        public array $permissions,
        public array $userRoles,
        public array $rolePermissions,
        public string $ownerId,
    ) {
    }

    public static function parse(string $json): self
    {
        if ($json === '' || strlen($json) > 5_000_000) {
            throw new \InvalidArgumentException('Legacy identity snapshot is empty or exceeds 5 MB.');
        }
        try {
            $raw = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Legacy identity snapshot is invalid JSON.', previous: $exception);
        }
        if (!is_array($raw) || array_is_list($raw) || ($raw['schema'] ?? null) !== 1) {
            throw new \InvalidArgumentException('Legacy identity snapshot schema is unsupported.');
        }
        $timezone = self::string($raw['timezone'] ?? null, 'timezone', 64);
        $zone = new DateTimeZone($timezone);

        $users = [];
        $userIds = [];
        foreach (self::rows($raw, 'users') as $row) {
            $id = self::id($row['id'] ?? null);
            if (isset($userIds[$id])) {
                throw new \InvalidArgumentException('Legacy user ID is duplicated.');
            }
            $userIds[$id] = true;
            $status = self::string($row['status'] ?? null, 'status', 16);
            if (!in_array($status, ['active', 'pending', 'blocked'], true)) {
                throw new \InvalidArgumentException('Legacy user status is invalid.');
            }
            $users[] = [
                'id' => $id,
                'display_name' => self::string($row['display_name'] ?? null, 'display_name', 120),
                'email' => self::nullableString($row['email'] ?? null, 'email', 254),
                'avatar_url' => self::nullableString($row['avatar_url'] ?? null, 'avatar_url', 2048),
                'status' => $status,
                'created_at' => self::requiredTimestamp($row['created_at'] ?? null, $zone),
                'last_login_at' => self::timestamp($row['last_login_at'] ?? null, $zone),
            ];
        }
        if ($users === []) {
            throw new \InvalidArgumentException('Legacy snapshot has no users.');
        }

        $roles = [];
        $roleNames = [];
        foreach (self::rows($raw, 'roles') as $row) {
            $name = self::string($row['name'] ?? null, 'role', 64);
            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $name) !== 1 || isset($roleNames[$name])) {
                throw new \InvalidArgumentException('Legacy role is invalid or duplicated.');
            }
            $roleNames[$name] = true;
            $roles[] = ['name' => $name, 'label' => self::string($row['label'] ?? null, 'role label', 120)];
        }
        $permissions = [];
        $permissionNames = [];
        foreach (self::rows($raw, 'permissions') as $row) {
            $name = self::string($row['name'] ?? null, 'permission', 120);
            if (($name !== '*' && preg_match('/^[a-z][a-z0-9_.-]{1,119}$/D', $name) !== 1)
                || isset($permissionNames[$name])) {
                throw new \InvalidArgumentException('Legacy permission is invalid or duplicated.');
            }
            $permissionNames[$name] = true;
            $permissions[] = ['name' => $name, 'label' => self::string($row['label'] ?? null, 'permission label', 160)];
        }
        $userRoles = [];
        $assignments = [];
        $owners = [];
        foreach (self::rows($raw, 'user_roles') as $row) {
            $id = self::id($row['user_id'] ?? null);
            $role = self::string($row['role_name'] ?? null, 'role_name', 64);
            if (!isset($userIds[$id]) || !isset($roleNames[$role]) || isset($assignments[$id . ':' . $role])) {
                throw new \InvalidArgumentException('Legacy user role assignment is invalid.');
            }
            $assignments[$id . ':' . $role] = true;
            $userRoles[] = ['user_id' => $id, 'role_name' => $role];
            if ($role === 'owner') {
                $owners[] = $id;
            }
        }
        if (count($owners) !== 1) {
            throw new \InvalidArgumentException('Legacy snapshot must contain exactly one Owner.');
        }
        $ownerActive = false;
        foreach ($users as $user) {
            if ($user['id'] === $owners[0] && $user['status'] === 'active') {
                $ownerActive = true;
            }
        }
        if (!$ownerActive) {
            throw new \InvalidArgumentException('Legacy Owner must be active.');
        }
        $rolePermissions = [];
        $roleAssignments = [];
        foreach (self::rows($raw, 'role_permissions') as $row) {
            $role = self::string($row['role_name'] ?? null, 'role_name', 64);
            $permission = self::string($row['permission_name'] ?? null, 'permission_name', 120);
            if (!isset($roleNames[$role]) || !isset($permissionNames[$permission])
                || isset($roleAssignments[$role . ':' . $permission])) {
                throw new \InvalidArgumentException('Legacy role permission assignment is invalid.');
            }
            $roleAssignments[$role . ':' . $permission] = true;
            $rolePermissions[] = ['role_name' => $role, 'permission_name' => $permission];
        }

        $identities = [];
        $identityKeys = [];
        $usersWithIdentity = [];
        foreach (self::rows($raw, 'identities') as $row) {
            $id = self::id($row['user_id'] ?? null);
            $provider = self::string($row['provider'] ?? null, 'provider', 32);
            $subject = self::string($row['provider_subject'] ?? null, 'provider_subject', 255);
            if (!isset($userIds[$id]) || !in_array($provider, ['github', 'google', 'microsoft', 'discord'], true)
                || isset($identityKeys[$provider . ':' . $subject])) {
                throw new \InvalidArgumentException('Legacy identity is invalid or duplicated.');
            }
            $identityKeys[$provider . ':' . $subject] = true;
            $usersWithIdentity[$id] = true;
            $verified = $row['email_verified'] ?? null;
            if (!in_array($verified, [0, 1, false, true], true)) {
                throw new \InvalidArgumentException('Legacy email verification flag is invalid.');
            }
            $identities[] = [
                'user_id' => $id,
                'provider' => $provider,
                'provider_subject' => $subject,
                'provider_login' => self::nullableString($row['provider_login'] ?? null, 'provider_login', 191) ?: $subject,
                'provider_email' => self::nullableString($row['provider_email'] ?? null, 'provider_email', 254),
                'email_verified' => $verified === 1 || $verified === true,
                'linked_at' => self::requiredTimestamp($row['linked_at'] ?? null, $zone),
                'last_used_at' => self::timestamp($row['last_used_at'] ?? null, $zone),
            ];
        }
        if (count($usersWithIdentity) !== count($users)) {
            throw new \InvalidArgumentException('Every imported user requires an external identity.');
        }
        return new self(hash('sha256', $json), $users, $identities, $roles, $permissions,
            $userRoles, $rolePermissions, $owners[0]);
    }

    /**
     * @param array<mixed> $source
     * @return list<array<string, mixed>>
     */
    private static function rows(array $source, string $key): array
    {
        $value = $source[$key] ?? null;
        if (!is_array($value) || !array_is_list($value)) {
            throw new \InvalidArgumentException(sprintf('Legacy snapshot %s must be a list.', $key));
        }
        $rows = [];
        foreach ($value as $row) {
            if (!is_array($row) || array_is_list($row)) {
                throw new \InvalidArgumentException(sprintf('Legacy snapshot %s contains an invalid row.', $key));
            }
            $normalized = [];
            foreach ($row as $field => $fieldValue) {
                if (!is_string($field)) {
                    throw new \InvalidArgumentException(sprintf('Legacy snapshot %s contains an invalid field.', $key));
                }
                $normalized[$field] = $fieldValue;
            }
            $rows[] = $normalized;
        }
        return $rows;
    }

    private static function id(mixed $value): string
    {
        if (is_int($value) && $value > 0) {
            return (string) $value;
        }
        if (is_string($value) && preg_match('/^[1-9][0-9]*$/D', $value) === 1) {
            return $value;
        }
        throw new \InvalidArgumentException('Legacy user ID is invalid.');
    }

    private static function string(mixed $value, string $field, int $limit): string
    {
        if (!is_string($value) || trim($value) === '' || strlen($value) > $limit) {
            throw new \InvalidArgumentException(sprintf('Legacy %s is invalid.', $field));
        }
        return $value;
    }

    private static function nullableString(mixed $value, string $field, int $limit): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_string($value) || strlen($value) > $limit) {
            throw new \InvalidArgumentException(sprintf('Legacy %s is invalid.', $field));
        }
        return $value;
    }

    private static function timestamp(mixed $value, DateTimeZone $zone): ?string
    {
        if ($value === null) {
            return null;
        }
        return self::requiredTimestamp($value, $zone);
    }

    private static function requiredTimestamp(mixed $value, DateTimeZone $zone): string
    {
        if (!is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/D', $value) !== 1) {
            throw new \InvalidArgumentException('Legacy timestamp is invalid.');
        }
        $time = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $zone);
        if ($time === false || $time->format('Y-m-d H:i:s') !== $value) {
            throw new \InvalidArgumentException('Legacy timestamp is invalid.');
        }
        return $time->format('Y-m-d\TH:i:s.uP');
    }
}
