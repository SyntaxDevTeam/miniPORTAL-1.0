<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\ConfigurationException;
use SyntaxDevTeam\MiniPortal\Core\Configuration\ConfigurationLoader;
use SyntaxDevTeam\MiniPortal\Core\Configuration\EnvironmentFileParser;
use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\DatabaseEngine;

final class ConfigurationLoaderTest extends TestCase
{
    public function testLoadsPostgreSqlAndKeepsPasswordOutOfDsn(): void
    {
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'APP_ENV' => 'development',
            'DATABASE_ENGINE' => 'postgresql',
            'DATABASE_HOST' => 'database.internal',
            'DATABASE_PORT' => '5432',
            'DATABASE_NAME' => 'miniportal',
            'DATABASE_USER' => 'portal',
            'DATABASE_PASSWORD' => 'highly-secret',
        ]);

        self::assertSame(ApplicationEnvironment::Development, $config->environment);
        self::assertNotNull($config->database);
        self::assertSame(DatabaseEngine::PostgreSql, $config->database->engine);
        self::assertStringNotContainsString('highly-secret', $config->database->connectionConfig()->dsn);
    }

    public function testRejectsPartialDatabaseConfiguration(): void
    {
        $this->expectException(ConfigurationException::class);
        (new ConfigurationLoader())->load('/path/without/env', [
            'DATABASE_ENGINE' => 'mysql',
        ]);
    }

    public function testLoadsExternalAuthenticationWithoutLocalPassword(): void
    {
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'APP_ENV' => 'production',
            'AUTH_GITHUB_CLIENT_ID' => 'client-id',
            'AUTH_GITHUB_CLIENT_SECRET' => 'client-secret',
            'AUTH_GITHUB_CALLBACK_URL' => 'https://portal.test/auth/github/callback',
            'AUTH_ADMIN_IDENTITIES' => 'github:12345',
            'SESSION_IDLE_SECONDS' => '900',
            'SESSION_ABSOLUTE_SECONDS' => '7200',
        ]);

        self::assertNotNull($config->authentication);
        self::assertTrue($config->authentication->permits('github', '12345'));
        self::assertFalse($config->authentication->permits('github', '999'));
        self::assertSame('github', $config->authentication->providers[0]->name);
        self::assertSame(900, $config->authentication->idleTimeoutSeconds);
    }

    public function testRejectsPartialExternalAuthentication(): void
    {
        $this->expectException(ConfigurationException::class);
        (new ConfigurationLoader())->load('/path/without/env', [
            'AUTH_GITHUB_CLIENT_ID' => 'client-id',
        ]);
    }

    public function testDatabaseBootstrapDoesNotRequireEnvironmentAllowList(): void
    {
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'AUTH_GITHUB_CLIENT_ID' => 'client-id',
            'AUTH_GITHUB_CLIENT_SECRET' => 'client-secret',
            'AUTH_GITHUB_CALLBACK_URL' => 'https://portal.test/auth/github/callback',
        ]);

        self::assertNotNull($config->authentication);
        self::assertSame([], $config->authentication->administratorIdentities);
    }

    public function testParserDoesNotExpandShellExpressions(): void
    {
        $values = (new EnvironmentFileParser())->parse(<<<'ENV'
DATABASE_PASSWORD="$(touch /tmp/never-execute)"
DATABASE_HOST=localhost # comment
ENV);

        self::assertSame('$(touch /tmp/never-execute)', $values['DATABASE_PASSWORD']);
        self::assertSame('localhost', $values['DATABASE_HOST']);
    }

    public function testParserRejectsDuplicateKeys(): void
    {
        $this->expectException(ConfigurationException::class);
        (new EnvironmentFileParser())->parse("APP_ENV=production\nAPP_ENV=testing\n");
    }

    public function testLegacyPrefixRemainsACompatibleFallback(): void
    {
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'MINIPORTAL_ENV' => 'testing',
            'MINIPORTAL_DATABASE_ENGINE' => 'mysql',
            'MINIPORTAL_DATABASE_HOST' => 'legacy.internal',
            'MINIPORTAL_DATABASE_PORT' => '3306',
            'MINIPORTAL_DATABASE_NAME' => 'legacy',
            'MINIPORTAL_DATABASE_USER' => 'legacy',
            'MINIPORTAL_DATABASE_PASSWORD' => 'secret',
        ]);

        self::assertSame(ApplicationEnvironment::Testing, $config->environment);
        self::assertSame('legacy.internal', $config->database?->host);
    }

    public function testCanonicalKeyWinsOverLegacyKeyInTheSameSource(): void
    {
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'APP_ENV' => 'development',
            'MINIPORTAL_ENV' => 'production',
        ]);

        self::assertSame(ApplicationEnvironment::Development, $config->environment);
    }
}
