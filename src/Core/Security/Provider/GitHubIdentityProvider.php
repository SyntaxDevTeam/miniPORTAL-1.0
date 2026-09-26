<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;

final class GitHubIdentityProvider extends AbstractOAuthProvider implements IdentityProvider
{
    public function name(): string { return 'github'; }
    public function label(): string { return 'GitHub'; }

    public function authorizationUrl(string $state, string $codeChallenge, string $_nonce): string
    {
        return $this->url('https://github.com/login/oauth/authorize', [
            'client_id' => $this->clientId,
            'redirect_uri' => $this->callbackUrl,
            'scope' => 'read:user user:email',
            'state' => $state,
            'code_challenge' => $codeChallenge,
            'code_challenge_method' => 'S256',
        ]);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $_nonce): ExternalIdentity
    {
        $token = $this->postForm('https://github.com/login/oauth/access_token', [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'code' => $code, 'redirect_uri' => $this->callbackUrl, 'code_verifier' => $codeVerifier,
        ]);
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('GitHub did not return an access token.');
        }
        $headers = ['Authorization' => 'Bearer ' . $accessToken, 'X-GitHub-Api-Version' => '2022-11-28'];
        $profile = $this->getJson('https://api.github.com/user', $headers);
        $subject = $profile['id'] ?? null;
        $login = $profile['login'] ?? null;
        if ((!is_int($subject) && !is_string($subject)) || !is_string($login) || $login === '') {
            throw new \RuntimeException('GitHub profile has no stable identity.');
        }
        $email = is_string($profile['email'] ?? null) && $profile['email'] !== '' ? $profile['email'] : null;
        $verified = false;
        if ($email === null) {
            foreach ($this->getJson('https://api.github.com/user/emails', $headers) as $item) {
                if (is_array($item) && ($item['primary'] ?? false) === true && ($item['verified'] ?? false) === true
                    && is_string($item['email'] ?? null)) {
                    $email = $item['email'];
                    $verified = true;
                    break;
                }
            }
        }
        return new ExternalIdentity('github', (string) $subject, $login, $email, $verified,
            is_string($profile['avatar_url'] ?? null) ? $profile['avatar_url'] : null);
    }
}
