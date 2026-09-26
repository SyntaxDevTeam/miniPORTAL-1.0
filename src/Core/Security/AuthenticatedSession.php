<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

final readonly class AuthenticatedSession
{
    public function __construct(
        public string $principalId,
        public int $createdAt,
        public int $lastSeenAt,
        public string $csrfToken,
    ) {
        if ($principalId === '' || $createdAt < 0 || $lastSeenAt < $createdAt || preg_match('/^[a-f0-9]{64}$/D', $csrfToken) !== 1) {
            throw new \InvalidArgumentException('Authenticated session data is invalid.');
        }
    }

    public function touchedAt(int $timestamp): self
    {
        return new self($this->principalId, $this->createdAt, $timestamp, $this->csrfToken);
    }
}
