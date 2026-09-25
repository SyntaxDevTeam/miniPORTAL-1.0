<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class TableColumn
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $numeric = false,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $id) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('Table column requires a valid ID and label.');
        }
    }
}
