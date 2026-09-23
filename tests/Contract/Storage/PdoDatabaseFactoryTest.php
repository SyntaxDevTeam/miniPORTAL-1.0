<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Storage;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\ProviderUnavailable;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\DatabaseEngine;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class PdoDatabaseFactoryTest extends TestCase
{
    public function testBuildsPortableMySqlServerConfiguration(): void
    {
        $config = PdoConnectionConfig::server(
            DatabaseEngine::MySql,
            'database.internal',
            'miniportal',
            'portal_user',
            'secret',
        );

        self::assertSame('mysql:host=database.internal;port=3306;dbname=miniportal;charset=utf8mb4', $config->dsn);
        self::assertSame(DatabaseEngine::MySql, $config->engine);
    }

    public function testBuildsPortablePostgreSqlServerConfiguration(): void
    {
        $config = PdoConnectionConfig::server(
            DatabaseEngine::PostgreSql,
            '2001:db8::1',
            'miniportal',
            'portal_user',
            'secret',
            55432,
        );

        self::assertSame('pgsql:host=[2001:db8::1];port=55432;dbname=miniportal', $config->dsn);
        self::assertSame(DatabaseEngine::PostgreSql, $config->engine);
    }

    public function testRejectsDsnInjectionInInstallerValues(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PdoConnectionConfig::server(
            DatabaseEngine::MySql,
            'localhost;dbname=other',
            'miniportal',
            'portal_user',
            'secret',
        );
    }

    public function testConnectionFailureUsesStableProviderException(): void
    {
        $this->expectException(ProviderUnavailable::class);

        (new PdoDatabaseFactory())->connect(
            new PdoConnectionConfig('miniportal-unsupported-driver:fixture'),
        );
    }
}
