<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class TableColumn
{
    public function __construct(
        public string $id,
        public string $label,
        public bool $numeric = false,
        public ?string $sortUrl = null,
        public ?SortDirection $sortDirection = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $id) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('Table column requires a valid ID and label.');
        }
        if ($sortUrl !== null && !str_starts_with($sortUrl, '/')
            && preg_match('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?(?:\/|$)/D', $sortUrl) !== 1) {
            throw new \InvalidArgumentException('Table sort URL must be relative or HTTPS.');
        }
        if ($sortDirection !== null && $sortUrl === null) {
            throw new \InvalidArgumentException('Active table sort direction requires a sort URL.');
        }
    }
}
