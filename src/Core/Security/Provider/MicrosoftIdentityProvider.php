<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;

final class MicrosoftIdentityProvider extends AbstractOAuthProvider implements IdentityProvider
{
    public function name(): string { return 'microsoft'; }
    public function label(): string { return 'Microsoft'; }

    public function authorizationUrl(string $state, string $codeChallenge, string $_nonce): string
    {
        return $this->url('https://login.microsoftonline.com/common/oauth2/v2.0/authorize', [
            'client_id' => $this->clientId, 'response_type' => 'code', 'redirect_uri' => $this->callbackUrl,
            'response_mode' => 'query', 'scope' => 'openid profile email User.Read', 'state' => $state,
            'code_challenge' => $codeChallenge, 'code_challenge_method' => 'S256',
        ]);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $_nonce): ExternalIdentity
    {
        $token = $this->postForm('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret, 'code' => $code,
            'code_verifier' => $codeVerifier, 'grant_type' => 'authorization_code', 'redirect_uri' => $this->callbackUrl,
        ]);
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Microsoft did not return an access token.');
        }
        $profile = $this->getJson('https://graph.microsoft.com/v1.0/me?$select=id,displayName,mail,userPrincipalName',
            ['Authorization' => 'Bearer ' . $accessToken]);
        $subject = $profile['id'] ?? null;
        $name = $profile['displayName'] ?? null;
        $email = $profile['mail'] ?? $profile['userPrincipalName'] ?? null;
        if (!is_string($subject) || $subject === '' || !is_string($name) || $name === '') {
            throw new \RuntimeException('Microsoft profile has no stable identity.');
        }
        return new ExternalIdentity('microsoft', $subject, $name,
            is_string($email) && $email !== '' ? $email : null, is_string($email) && $email !== '');
    }
}
