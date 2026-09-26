<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

final readonly class ExternalIdentity
{
    public function __construct(
        public string $provider,
        public string $subject,
        public string $displayName,
        public ?string $email = null,
        public bool $emailVerified = false,
        public ?string $avatarUrl = null,
    ) {
        if (!in_array($provider, ['github', 'google', 'microsoft', 'discord'], true)
            || $subject === '' || strlen($subject) > 255 || $displayName === '') {
            throw new \InvalidArgumentException('External identity is invalid.');
        }
    }

    public function principalId(): string
    {
        return $this->provider . ':' . $this->subject;
    }
}
