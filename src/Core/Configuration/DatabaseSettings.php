<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\DatabaseEngine;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;

final readonly class DatabaseSettings
{
    public function __construct(
        public DatabaseEngine $engine,
        public string $host,
        public int $port,
        public string $database,
        public string $username,
        private string $password,
    ) {
    }

    public function connectionConfig(): PdoConnectionConfig
    {
        return PdoConnectionConfig::server(
            $this->engine,
            $this->host,
            $this->database,
            $this->username,
            $this->password,
            $this->port,
        );
    }
}
