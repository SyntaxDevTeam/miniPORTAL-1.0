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

        foreach (['github', 'google', 'microsoft', 'discord'] as $name) {
            parse_str((string) parse_url($providers[$name]->authorizationUrl('state-value', 'pkce-challenge', 'oidc-nonce'), PHP_URL_QUERY), $query);
            self::assertSame('pkce-challenge', $query['code_challenge'] ?? null, $name);
            self::assertSame('S256', $query['code_challenge_method'] ?? null, $name);
        }
        parse_str((string) parse_url($providers['google']->authorizationUrl('state-value', 'pkce-challenge', 'oidc-nonce'), PHP_URL_QUERY), $google);
        self::assertSame('oidc-nonce', $google['nonce'] ?? null);
    }

    public function testGithubExchangesCodeWithPkceAndLoadsStableIdentity(): void
    {
        $http = new RecordingHttpClient([
            new HttpResponse(200, [], json_encode(['access_token' => 'github-token'], JSON_THROW_ON_ERROR)),
            new HttpResponse(200, [], json_encode([
                'id' => 123,
                'login' => 'octocat',
                'email' => null,
                'avatar_url' => 'https://avatars.test/123',
            ], JSON_THROW_ON_ERROR)),
            new HttpResponse(200, [], json_encode([[
                'email' => 'octocat@example.test',
                'primary' => true,
                'verified' => true,
            ]], JSON_THROW_ON_ERROR)),
        ]);
        $provider = new GitHubIdentityProvider($http, 'client', 'secret', 'https://portal.test/auth/github/callback');

        $identity = $provider->resolveIdentity('authorization-code', 'pkce-verifier', 'unused-nonce');

        self::assertSame('github:123', $identity->principalId());
        self::assertSame('octocat@example.test', $identity->email);
        self::assertTrue($identity->emailVerified);
        self::assertCount(3, $http->requests);
        parse_str($http->requests[0]->body, $tokenForm);
        self::assertSame('authorization-code', $tokenForm['code'] ?? null);
        self::assertSame('pkce-verifier', $tokenForm['code_verifier'] ?? null);
        self::assertSame('Bearer github-token', $http->requests[1]->headers['Authorization'] ?? null);
    }

    public function testDiscordExchangesCodeWithPkceAndLoadsStableIdentity(): void
    {
        $http = new RecordingHttpClient([
            new HttpResponse(200, [], json_encode(['access_token' => 'discord-token'], JSON_THROW_ON_ERROR)),
            new HttpResponse(200, [], json_encode([
                'id' => '456',
                'username' => 'portal-user',
                'email' => 'user@example.test',
                'verified' => true,
                'avatar' => 'avatar-hash',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $provider = new DiscordIdentityProvider($http, 'client', 'secret', 'https://portal.test/auth/discord/callback');

        $identity = $provider->resolveIdentity('authorization-code', 'pkce-verifier', 'unused-nonce');

        self::assertSame('discord:456', $identity->principalId());
        self::assertTrue($identity->emailVerified);
        parse_str($http->requests[0]->body, $tokenForm);
        self::assertSame('pkce-verifier', $tokenForm['code_verifier'] ?? null);
        self::assertSame('Bearer discord-token', $http->requests[1]->headers['Authorization'] ?? null);
    }

    public function testMicrosoftExchangesCodeWithPkceAndLoadsStableIdentity(): void
    {
        $http = new RecordingHttpClient([
            new HttpResponse(200, [], json_encode(['access_token' => 'microsoft-token'], JSON_THROW_ON_ERROR)),
            new HttpResponse(200, [], json_encode([
                'id' => '789',
                'displayName' => 'Portal User',
                'mail' => 'user@example.test',
            ], JSON_THROW_ON_ERROR)),
        ]);
        $provider = new MicrosoftIdentityProvider($http, 'client', 'secret', 'https://portal.test/auth/microsoft/callback');

        $identity = $provider->resolveIdentity('authorization-code', 'pkce-verifier', 'unused-nonce');

        self::assertSame('microsoft:789', $identity->principalId());
        parse_str($http->requests[0]->body, $tokenForm);
        self::assertSame('pkce-verifier', $tokenForm['code_verifier'] ?? null);
        self::assertSame('Bearer microsoft-token', $http->requests[1]->headers['Authorization'] ?? null);
    }
}

final class RecordingHttpClient implements HttpClient
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @param list<HttpResponse> $responses */
    public function __construct(private array $responses)
    {
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        return array_shift($this->responses)
            ?? throw new \LogicException('Unexpected identity provider request.');
    }
}
