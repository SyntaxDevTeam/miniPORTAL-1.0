<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

final readonly class UserAccount
{
    /**
     * @param list<string> $roles
     * @param list<string> $permissions
     */
    public function __construct(
        public string $id,
        public string $displayName,
        public ?string $email,
        public ?string $avatarUrl,
        public AccountStatus $status,
        public array $roles,
        public array $permissions,
    ) {
        if (preg_match('/^(?:[a-f0-9]{32}|(?:github|google|microsoft|discord):[^:\s]{1,255})$/D', $id) !== 1
            || trim($displayName) === '') {
            throw new \InvalidArgumentException('User account is invalid.');
        }
        foreach ($roles as $role) {
            if (preg_match('/^[a-z][a-z0-9_]{1,63}$/D', $role) !== 1) {
                throw new \InvalidArgumentException('User account role is invalid.');
            }
        }
        foreach ($permissions as $permission) {
            if ($permission !== '*' && preg_match('/^[a-z][a-z0-9_.-]{1,119}$/D', $permission) !== 1) {
                throw new \InvalidArgumentException('User account permission is invalid.');
            }
        }
    }

    public function canAccessAdmin(): bool
    {
        return $this->status === AccountStatus::Active
            && (in_array('*', $this->permissions, true) || in_array('admin.access', $this->permissions, true));
    }
}
