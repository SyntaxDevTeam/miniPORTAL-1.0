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
            'MINIPORTAL_ENV' => 'development',
            'MINIPORTAL_DATABASE_ENGINE' => 'postgresql',
            'MINIPORTAL_DATABASE_HOST' => 'database.internal',
            'MINIPORTAL_DATABASE_PORT' => '5432',
            'MINIPORTAL_DATABASE_NAME' => 'miniportal',
            'MINIPORTAL_DATABASE_USER' => 'portal',
            'MINIPORTAL_DATABASE_PASSWORD' => 'highly-secret',
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
            'MINIPORTAL_DATABASE_ENGINE' => 'mysql',
        ]);
    }

    public function testLoadsExternalAuthenticationWithoutLocalPassword(): void
    {
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'MINIPORTAL_ENV' => 'production',
            'MINIPORTAL_AUTH_GITHUB_CLIENT_ID' => 'client-id',
            'MINIPORTAL_AUTH_GITHUB_CLIENT_SECRET' => 'client-secret',
            'MINIPORTAL_AUTH_GITHUB_CALLBACK_URL' => 'https://portal.test/auth/github/callback',
            'MINIPORTAL_AUTH_ADMIN_IDENTITIES' => 'github:12345',
            'MINIPORTAL_SESSION_IDLE_SECONDS' => '900',
            'MINIPORTAL_SESSION_ABSOLUTE_SECONDS' => '7200',
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
            'MINIPORTAL_AUTH_GITHUB_CLIENT_ID' => 'client-id',
        ]);
    }

    public function testParserDoesNotExpandShellExpressions(): void
    {
        $values = (new EnvironmentFileParser())->parse(<<<'ENV'
MINIPORTAL_DATABASE_PASSWORD="$(touch /tmp/never-execute)"
MINIPORTAL_DATABASE_HOST=localhost # comment
ENV);

        self::assertSame('$(touch /tmp/never-execute)', $values['MINIPORTAL_DATABASE_PASSWORD']);
        self::assertSame('localhost', $values['MINIPORTAL_DATABASE_HOST']);
    }

    public function testParserRejectsDuplicateKeys(): void
    {
        $this->expectException(ConfigurationException::class);
        (new EnvironmentFileParser())->parse("MINIPORTAL_ENV=production\nMINIPORTAL_ENV=testing\n");
    }
}
