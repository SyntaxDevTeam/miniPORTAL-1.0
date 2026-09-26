<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

final readonly class AuthenticationSettings
{
    public function __construct(
        public string $username,
        private string $passwordHash,
        public int $idleTimeoutSeconds = 1800,
        public int $absoluteTimeoutSeconds = 28800,
    ) {
        if (preg_match('/^[A-Za-z0-9_.@-]{3,128}$/D', $username) !== 1) {
            throw new \InvalidArgumentException('Administrator username is invalid.');
        }
        if (password_get_info($passwordHash)['algoName'] === 'unknown') {
            throw new \InvalidArgumentException('Administrator password must be stored as a password_hash value.');
        }
        if ($idleTimeoutSeconds < 300 || $absoluteTimeoutSeconds < $idleTimeoutSeconds) {
            throw new \InvalidArgumentException('Authentication timeouts are invalid.');
        }
    }

    public function verifies(string $username, string $password): bool
    {
        return hash_equals($this->username, $username) && password_verify($password, $this->passwordHash);
    }
}
