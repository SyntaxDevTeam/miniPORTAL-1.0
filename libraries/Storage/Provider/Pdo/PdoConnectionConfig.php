<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo;

final readonly class PdoConnectionConfig
{
    /**
     * @param array<int, mixed> $options
     */
    public function __construct(
        public string $dsn,
        public ?string $username = null,
        public ?string $password = null,
        public array $options = [],
        public ?DatabaseEngine $engine = null,
    ) {
        if (trim($dsn) === '') {
            throw new \InvalidArgumentException('PDO DSN cannot be empty.');
        }
    }

    /** @param array<int, mixed> $options */
    public static function server(
        DatabaseEngine $engine,
        string $host,
        string $database,
        string $username,
        string $password,
        ?int $port = null,
        array $options = [],
    ): self {
        $host = self::normalizeHost($host);
        self::validateDatabaseName($database);
        if ($username === '') {
            throw new \InvalidArgumentException('Database username cannot be empty.');
        }

        $port ??= $engine->defaultPort();
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Database port must be between 1 and 65535.');
        }

        $dsn = match ($engine) {
            DatabaseEngine::MySql => sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                $host,
                $port,
                $database,
            ),
            DatabaseEngine::PostgreSql => sprintf(
                'pgsql:host=%s;port=%d;dbname=%s',
                $host,
                $port,
                $database,
            ),
        };

        return new self($dsn, $username, $password, $options, $engine);
    }

    private static function normalizeHost(string $host): string
    {
        $unwrapped = str_starts_with($host, '[') && str_ends_with($host, ']')
            ? substr($host, 1, -1)
            : $host;
        if (filter_var($unwrapped, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
            return '[' . $unwrapped . ']';
        }
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
            || preg_match('/^[A-Za-z0-9](?:[A-Za-z0-9.-]{0,251}[A-Za-z0-9])?$/D', $host) === 1) {
            return $host;
        }

        if ($host !== '') {
            throw new \InvalidArgumentException('Database host contains unsupported characters.');
        }

        throw new \InvalidArgumentException('Database host cannot be empty.');
    }

    private static function validateDatabaseName(string $database): void
    {
        if (preg_match('/^[A-Za-z][A-Za-z0-9_]{0,62}$/D', $database) !== 1) {
            throw new \InvalidArgumentException('Database name must be a portable SQL identifier.');
        }
    }
}
