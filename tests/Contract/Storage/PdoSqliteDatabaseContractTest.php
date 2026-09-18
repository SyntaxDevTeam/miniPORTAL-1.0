<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Storage;

use PDO;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class PdoSqliteDatabaseContractTest extends DatabaseContractTestCase
{
    private Database $database;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }

        $this->database = (new PdoDatabaseFactory())->connect(
            new PdoConnectionConfig('sqlite::memory:'),
        );

        parent::setUp();
    }

    protected function database(): Database
    {
        return $this->database;
    }
}
