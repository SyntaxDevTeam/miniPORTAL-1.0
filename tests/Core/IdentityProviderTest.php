<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DiscordIdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\GitHubIdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\GoogleIdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\MicrosoftIdentityProvider;
use SyntaxDevTeam\MiniPortal\Library\Http\Contract\HttpClient;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpRequest;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpResponse;

final class IdentityProviderTest extends TestCase
{
    public function testAllSupportedProvidersCarryStateAndExpectedProtocolProtections(): void
    {
        $http = new class implements HttpClient {
            public function send(HttpRequest $_request): HttpResponse
            {
                throw new \LogicException('Authorization URL generation must not perform HTTP requests.');
            }
        };
        $callback = 'https://portal.test/auth/callback';
        $providers = [
            'github' => new GitHubIdentityProvider($http, 'client', 'secret', $callback),
            'google' => new GoogleIdentityProvider($http, 'client', 'secret', $callback),
            'microsoft' => new MicrosoftIdentityProvider($http, 'client', 'secret', $callback),
            'discord' => new DiscordIdentityProvider($http, 'client', 'secret', $callback),
        ];

        foreach ($providers as $name => $provider) {
            $url = $provider->authorizationUrl('state-value', 'pkce-challenge', 'oidc-nonce');
            self::assertStringStartsWith('https://', $url);
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            self::assertSame('state-value', $query['state'] ?? null, $name);
            self::assertSame($callback, $query['redirect_uri'] ?? null, $name);
        }

        foreach (['github', 'google', 'microsoft'] as $name) {
            parse_str((string) parse_url($providers[$name]->authorizationUrl('state-value', 'pkce-challenge', 'oidc-nonce'), PHP_URL_QUERY), $query);
            self::assertSame('pkce-challenge', $query['code_challenge'] ?? null, $name);
            self::assertSame('S256', $query['code_challenge_method'] ?? null, $name);
        }
        parse_str((string) parse_url($providers['google']->authorizationUrl('state-value', 'pkce-challenge', 'oidc-nonce'), PHP_URL_QUERY), $google);
        self::assertSame('oidc-nonce', $google['nonce'] ?? null);
    }
}
