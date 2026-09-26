<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

final readonly class AuthenticationSettings
{
    /**
     * @param list<IdentityProviderSettings> $providers
     * @param list<string> $administratorIdentities Stable `provider:subject` identifiers.
     */
    public function __construct(
        public array $providers,
        public array $administratorIdentities,
        public int $idleTimeoutSeconds = 1800,
        public int $absoluteTimeoutSeconds = 28800,
    ) {
        if ($providers === []) {
            throw new \InvalidArgumentException('At least one identity provider must be configured.');
        }
        $providerNames = [];
        foreach ($providers as $provider) {
            if (isset($providerNames[$provider->name])) {
                throw new \InvalidArgumentException('Identity provider configuration is invalid.');
            }
            $providerNames[$provider->name] = true;
        }
        if ($administratorIdentities === []) {
            throw new \InvalidArgumentException('At least one administrator identity must be configured.');
        }
        foreach ($administratorIdentities as $identity) {
            if (preg_match('/^(github|google|microsoft|discord):[^:\s]{1,255}$/D', $identity) !== 1) {
                throw new \InvalidArgumentException('Administrator identity is invalid.');
            }
            [$identityProvider] = explode(':', $identity, 2);
            if (!isset($providerNames[$identityProvider])) {
                throw new \InvalidArgumentException('Administrator identity refers to an unconfigured provider.');
            }
        }
        if ($idleTimeoutSeconds < 300 || $absoluteTimeoutSeconds < $idleTimeoutSeconds) {
            throw new \InvalidArgumentException('Authentication timeouts are invalid.');
        }
    }

    public function permits(string $provider, string $subject): bool
    {
        return in_array($provider . ':' . $subject, $this->administratorIdentities, true);
    }
}
