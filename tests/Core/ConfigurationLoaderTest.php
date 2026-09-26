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

    public function testLoadsAdministratorAuthenticationWithoutPlaintextPassword(): void
    {
        $hash = password_hash('secret', PASSWORD_DEFAULT);
        $config = (new ConfigurationLoader())->load('/path/without/env', [
            'MINIPORTAL_ENV' => 'production',
            'MINIPORTAL_ADMIN_USERNAME' => 'admin@example.test',
            'MINIPORTAL_ADMIN_PASSWORD_HASH' => $hash,
            'MINIPORTAL_SESSION_IDLE_SECONDS' => '900',
            'MINIPORTAL_SESSION_ABSOLUTE_SECONDS' => '7200',
        ]);

        self::assertNotNull($config->authentication);
        self::assertTrue($config->authentication->verifies('admin@example.test', 'secret'));
        self::assertFalse($config->authentication->verifies('admin@example.test', 'wrong'));
        self::assertSame(900, $config->authentication->idleTimeoutSeconds);
    }

    public function testRejectsPartialAdministratorAuthentication(): void
    {
        $this->expectException(ConfigurationException::class);
        (new ConfigurationLoader())->load('/path/without/env', [
            'MINIPORTAL_ADMIN_USERNAME' => 'admin',
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
