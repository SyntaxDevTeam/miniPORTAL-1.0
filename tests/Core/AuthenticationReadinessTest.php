<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\ApplicationConfig;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\DatabaseSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Diagnostics\AuthenticationReadiness;
use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\DatabaseEngine;

final class AuthenticationReadinessTest extends TestCase
{
    public function testProductionIsReadyWithHttpsProviderAndDatabaseStorage(): void
    {
        $readiness = new AuthenticationReadiness(new ApplicationConfig(
            ApplicationEnvironment::Production,
            new DatabaseSettings(DatabaseEngine::MySql, 'database', 3306, 'portal', 'portal', 'secret'),
            new AuthenticationSettings([
                new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
            ], []),
        ));

        self::assertTrue($readiness->isReady());
    }

    public function testMissingProviderIsReportedAsNotReady(): void
    {
        $readiness = new AuthenticationReadiness(new ApplicationConfig(ApplicationEnvironment::Production));

        self::assertFalse($readiness->isReady());
        self::assertSame('auth_providers', $readiness->checks()[0]->name);
    }

    public function testProductionRejectsInsecureCallbackAndMissingStorage(): void
    {
        $readiness = new AuthenticationReadiness(new ApplicationConfig(
            ApplicationEnvironment::Production,
            authentication: new AuthenticationSettings([
                new IdentityProviderSettings('discord', 'client', 'secret', 'http://portal.test/auth/discord/callback'),
            ], []),
        ));
        $checks = [];
        foreach ($readiness->checks() as $check) {
            $checks[$check->name] = $check->ok;
        }

        self::assertFalse($readiness->isReady());
        self::assertFalse($checks['auth_callbacks']);
        self::assertFalse($checks['auth_storage']);
    }
}
