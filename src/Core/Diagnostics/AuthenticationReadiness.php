<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Diagnostics;

use SyntaxDevTeam\MiniPortal\Core\Configuration\ApplicationConfig;

final readonly class AuthenticationReadiness
{
    public function __construct(private ApplicationConfig $config)
    {
    }

    /** @return list<DoctorCheck> */
    public function checks(): array
    {
        $authentication = $this->config->authentication;
        if ($authentication === null) {
            return [new DoctorCheck('auth_providers', false, 'No OAuth/OIDC provider is configured')];
        }

        $providerNames = array_map(static fn ($provider): string => $provider->name, $authentication->providers);
        $callbacksSecure = true;
        foreach ($authentication->providers as $provider) {
            $scheme = parse_url($provider->callbackUrl, PHP_URL_SCHEME);
            if ($this->config->environment->isProduction() && $scheme !== 'https') {
                $callbacksSecure = false;
            }
        }
        $persistent = $this->config->database !== null;
        $fallbackReady = $authentication->administratorIdentities !== [];

        return [
            new DoctorCheck('auth_providers', true, 'Configured: ' . implode(', ', $providerNames)),
            new DoctorCheck(
                'auth_callbacks',
                $callbacksSecure,
                $callbacksSecure ? 'Callback URLs satisfy environment policy' : 'Production callback URLs must use HTTPS',
            ),
            new DoctorCheck(
                'auth_storage',
                $persistent || $fallbackReady,
                $persistent
                    ? 'Database-backed identities and roles enabled'
                    : ($fallbackReady
                        ? 'Database-less allow-list fallback enabled'
                        : 'Database configuration is required when no bootstrap allow-list is present'),
            ),
            new DoctorCheck(
                'openssl',
                extension_loaded('openssl'),
                extension_loaded('openssl') ? 'ext-openssl loaded' : 'ext-openssl missing',
            ),
        ];
    }

    public function isReady(): bool
    {
        foreach ($this->checks() as $check) {
            if (!$check->ok) {
                return false;
            }
        }
        return true;
    }
}
