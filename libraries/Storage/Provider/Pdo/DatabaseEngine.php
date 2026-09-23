<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo;

enum DatabaseEngine: string
{
    case MySql = 'mysql';
    case PostgreSql = 'pgsql';

    public function defaultPort(): int
    {
        return match ($this) {
            self::MySql => 3306,
            self::PostgreSql => 5432,
        };
    }

    public function requiredExtension(): string
    {
        return match ($this) {
            self::MySql => 'pdo_mysql',
            self::PostgreSql => 'pdo_pgsql',
        };
    }
}
