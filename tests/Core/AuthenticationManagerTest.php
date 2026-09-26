<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArraySessionStore;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class AuthenticationManagerTest extends TestCase
{
    public function testLoginRotatesSessionAndProvidesCsrfProtectedPrincipal(): void
    {
        $store = new ArraySessionStore();
        $auth = new AuthenticationManager(
            new AuthenticationSettings('admin', password_hash('correct horse', PASSWORD_DEFAULT)),
            $store,
            new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z')),
        );

        self::assertFalse($auth->login('admin', 'wrong'));
        self::assertTrue($auth->login('admin', 'correct horse'));
        self::assertSame(1, $store->regenerations);
        self::assertSame('admin', $auth->current()?->principalId);
        self::assertTrue($auth->verifyCsrf($auth->csrfToken()));
        self::assertFalse($auth->verifyCsrf('invalid'));
    }

    public function testIdleAndAbsoluteTimeoutsInvalidateSession(): void
    {
        $clock = new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z'));
        $store = new ArraySessionStore();
        $auth = new AuthenticationManager(
            new AuthenticationSettings('admin', password_hash('secret', PASSWORD_DEFAULT), 300, 600),
            $store,
            $clock,
        );
        self::assertTrue($auth->login('admin', 'secret'));

        $clock->advance('PT301S');
        self::assertNull($auth->current());
    }

    public function testLogoutInvalidatesAuthenticatedSession(): void
    {
        $store = new ArraySessionStore();
        $auth = new AuthenticationManager(
            new AuthenticationSettings('admin', password_hash('secret', PASSWORD_DEFAULT)),
            $store,
            new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z')),
        );
        $auth->login('admin', 'secret');
        $auth->logout();

        self::assertNull($auth->current());
    }
}
