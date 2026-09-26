<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;
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

        self::assertFalse($auth->login(new UserAccount(str_repeat('a', 32), 'Pending', null, null, AccountStatus::Pending, ['user'], [])));
        self::assertTrue($auth->login($this->owner()));
        self::assertSame(1, $store->regenerations);
        $session = $auth->current();
        self::assertNotNull($session);
        self::assertSame(str_repeat('b', 32), $session->principalId);
        self::assertSame(['*'], $session->permissions);
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
        self::assertTrue($auth->login($this->owner()));

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
        $auth->login($this->owner());
        $auth->logout();

        self::assertNull($auth->current());
    }

    private function settings(int $idle = 1800, int $absolute = 28800): AuthenticationSettings
    {
        return new AuthenticationSettings([
            new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
        ], ['github:123'], $idle, $absolute);
    }

    private function owner(): UserAccount
    {
        return new UserAccount(str_repeat('b', 32), 'Administrator', null, null, AccountStatus::Active, ['owner'], ['*']);
    }
}
