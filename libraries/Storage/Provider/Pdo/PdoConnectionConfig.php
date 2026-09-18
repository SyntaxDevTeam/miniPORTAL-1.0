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
    ) {
        if (trim($dsn) === '') {
            throw new \InvalidArgumentException('PDO DSN cannot be empty.');
        }
    }
}
