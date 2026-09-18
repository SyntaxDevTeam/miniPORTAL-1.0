<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Model;

final readonly class SqlStatement
{
    /**
     * @param array<int|string, string|int|float|bool|null> $parameters
     */
    public function __construct(
        public string $sql,
        public array $parameters = [],
    ) {
        if (trim($sql) === '') {
            throw new \InvalidArgumentException('SQL statement cannot be empty.');
        }

        foreach ($parameters as $name => $_value) {
            if (is_int($name)) {
                if ($name < 0) {
                    throw new \InvalidArgumentException('Positional SQL parameter indexes cannot be negative.');
                }
                continue;
            }

            if (preg_match('/^:?[A-Za-z_][A-Za-z0-9_]*$/', $name) !== 1) {
                throw new \InvalidArgumentException(sprintf(
                    'Invalid named SQL parameter: %s',
                    $name,
                ));
            }
        }
    }
}
