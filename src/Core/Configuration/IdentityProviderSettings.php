<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

final readonly class IdentityProviderSettings
{
    public function __construct(
        public string $name,
        public string $clientId,
        public string $clientSecret,
        public string $callbackUrl,
    ) {
        if (!in_array($name, ['github', 'google', 'microsoft', 'discord'], true)
            || $clientId === '' || $clientSecret === '') {
            throw new \InvalidArgumentException('Identity provider credentials are invalid.');
        }
        $parts = parse_url($callbackUrl);
        if ($parts === false || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('Identity provider callback URL is invalid.');
        }
    }
}
