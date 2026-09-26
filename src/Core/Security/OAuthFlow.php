<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\OAuthStateStore;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;

final readonly class OAuthFlow
{
    public function __construct(
        private IdentityProviderRegistry $providers,
        private OAuthStateStore $states,
        private AuthenticationManager $authentication,
        private Clock $clock,
    ) {
    }

    public function start(string $providerName): string
    {
        $provider = $this->providers->get($providerName)
            ?? throw new \InvalidArgumentException('Unknown identity provider.');
        $state = new OAuthState(
            $providerName,
            bin2hex(random_bytes(32)),
            rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '='),
            bin2hex(random_bytes(32)),
            $this->clock->now()->getTimestamp(),
        );
        $this->states->save($state);

        return $provider->authorizationUrl($state->state, $state->codeChallenge(), $state->nonce);
    }

    public function complete(string $providerName, ?string $stateValue, ?string $code): bool
    {
        if ($stateValue === null || $code === null || $code === '') {
            return false;
        }
        $provider = $this->providers->get($providerName);
        if ($provider === null) {
            return false;
        }
        $state = $this->states->consume($providerName, $stateValue, $this->clock->now()->getTimestamp());
        if ($state === null) {
            return false;
        }
        $identity = $provider->resolveIdentity($code, $state->codeVerifier, $state->nonce);

        return $this->authentication->login($identity);
    }
}
