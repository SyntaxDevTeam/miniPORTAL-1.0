<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArraySessionStore;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class AuthenticationManagerTest extends TestCase
{
    public function testLoginRotatesSessionAndProvidesCsrfProtectedPrincipal(): void
    {
        $store = new ArraySessionStore();
        $auth = new AuthenticationManager(
            $this->settings(),
            $store,
            new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z')),
        );

        self::assertFalse($auth->login(new ExternalIdentity('github', '999', 'Intruder')));
        self::assertTrue($auth->login(new ExternalIdentity('github', '123', 'Administrator')));
        self::assertSame(1, $store->regenerations);
        self::assertSame('github:123', $auth->current()?->principalId);
        self::assertTrue($auth->verifyCsrf($auth->csrfToken()));
        self::assertFalse($auth->verifyCsrf('invalid'));
    }

    public function testIdleAndAbsoluteTimeoutsInvalidateSession(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z'));
        $store = new ArraySessionStore();
        $auth = new AuthenticationManager(
            $this->settings(300, 600),
            $store,
            $clock,
        );
        self::assertTrue($auth->login(new ExternalIdentity('github', '123', 'Administrator')));

        $clock->advance('PT301S');
        self::assertNull($auth->current());
    }

    public function testLogoutInvalidatesAuthenticatedSession(): void
    {
        $store = new ArraySessionStore();
        $auth = new AuthenticationManager(
            $this->settings(),
            $store,
            new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z')),
        );
        $auth->login(new ExternalIdentity('github', '123', 'Administrator'));
        $auth->logout();

        self::assertNull($auth->current());
    }

    private function settings(int $idle = 1800, int $absolute = 28800): AuthenticationSettings
    {
        return new AuthenticationSettings([
            new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
        ], ['github:123'], $idle, $absolute);
    }
}
