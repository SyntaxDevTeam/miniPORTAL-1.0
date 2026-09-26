<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;

final class DiscordIdentityProvider extends AbstractOAuthProvider implements IdentityProvider
{
    public function name(): string { return 'discord'; }
    public function label(): string { return 'Discord'; }

    public function authorizationUrl(string $state, string $_codeChallenge, string $_nonce): string
    {
        return $this->url('https://discord.com/oauth2/authorize', [
            'response_type' => 'code', 'client_id' => $this->clientId, 'scope' => 'identify email',
            'state' => $state, 'redirect_uri' => $this->callbackUrl, 'prompt' => 'consent',
        ]);
    }

    public function resolveIdentity(string $code, string $_codeVerifier, string $_nonce): ExternalIdentity
    {
        $token = $this->postForm('https://discord.com/api/v10/oauth2/token', [
            'client_id' => $this->clientId, 'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code', 'code' => $code, 'redirect_uri' => $this->callbackUrl,
        ]);
        $accessToken = $token['access_token'] ?? null;
        if (!is_string($accessToken) || $accessToken === '') {
            throw new \RuntimeException('Discord did not return an access token.');
        }
        $profile = $this->getJson('https://discord.com/api/v10/users/@me', ['Authorization' => 'Bearer ' . $accessToken]);
        $subject = $profile['id'] ?? null;
        $login = $profile['global_name'] ?? $profile['username'] ?? null;
        if (!is_string($subject) || $subject === '' || !is_string($login) || $login === '') {
            throw new \RuntimeException('Discord profile has no stable identity.');
        }
        $avatar = $profile['avatar'] ?? null;
        return new ExternalIdentity('discord', $subject, $login,
            is_string($profile['email'] ?? null) ? $profile['email'] : null,
            ($profile['verified'] ?? false) === true,
            is_string($avatar) && $avatar !== '' ? "https://cdn.discordapp.com/avatars/{$subject}/{$avatar}.png" : null);
    }
}
