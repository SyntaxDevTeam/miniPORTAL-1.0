<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\IdentityProviderRegistry;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthFlow;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArrayOAuthStateStore;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArraySessionStore;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class OAuthFlowTest extends TestCase
{
    public function testCompletesOneTimePkceProtectedLoginForPermittedIdentity(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z'));
        $sessions = new ArraySessionStore();
        $authentication = new AuthenticationManager(new AuthenticationSettings([
            new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
        ], ['github:123']), $sessions, $clock);
        $provider = new StubIdentityProvider();
        $flow = new OAuthFlow(
            new IdentityProviderRegistry([$provider]),
            new ArrayOAuthStateStore(),
            $authentication,
            $clock,
        );

        $url = $flow->start('github');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $state = $query['state'] ?? null;
        $challenge = $query['challenge'] ?? null;
        self::assertIsString($state);
        self::assertIsString($challenge);
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D', $challenge);

        self::assertTrue($flow->complete('github', $state, 'authorization-code'));
        self::assertSame('github:123', $authentication->current()?->principalId);
        self::assertFalse($flow->complete('github', $state, 'replayed-code'));
        self::assertSame(1, $provider->resolutions);
    }

    public function testRejectsExpiredStateBeforeCallingProvider(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z'));
        $provider = new StubIdentityProvider();
        $flow = new OAuthFlow(
            new IdentityProviderRegistry([$provider]),
            new ArrayOAuthStateStore(),
            new AuthenticationManager(new AuthenticationSettings([
                new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
            ], ['github:123']), new ArraySessionStore(), $clock),
            $clock,
        );

        $url = $flow->start('github');
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
        $clock->advance('PT601S');

        self::assertFalse($flow->complete('github', is_string($query['state'] ?? null) ? $query['state'] : null, 'code'));
        self::assertSame(0, $provider->resolutions);
    }
}

final class StubIdentityProvider implements IdentityProvider
{
    public int $resolutions = 0;

    public function name(): string { return 'github'; }
    public function label(): string { return 'GitHub'; }

    public function authorizationUrl(string $state, string $codeChallenge, string $nonce): string
    {
        return 'https://github.test/authorize?' . http_build_query([
            'state' => $state, 'challenge' => $codeChallenge, 'nonce' => $nonce,
        ]);
    }

    public function resolveIdentity(string $code, string $codeVerifier, string $nonce): ExternalIdentity
    {
        $this->resolutions++;
        if ($code === '' || strlen($codeVerifier) < 43 || strlen($nonce) !== 64) {
            throw new \RuntimeException('Invalid authorization response.');
        }
        return new ExternalIdentity('github', '123', 'Administrator');
    }
}
