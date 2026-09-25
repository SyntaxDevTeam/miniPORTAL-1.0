<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class TableRow
{
    /** @param array<string, string|int|float|null> $cells */
    public function __construct(
        public ?string $id,
        private array $cells,
    ) {
        if ($id !== null && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $id) !== 1) {
            throw new \InvalidArgumentException('Table row ID must be a safe stable identifier.');
        }
        if ($cells === []) {
            throw new \InvalidArgumentException('Table row requires at least one cell.');
        }
    }

    /** @param array<string, string|int|float|null> $cells */
    public static function anonymous(array $cells): self
    {
        return new self(null, $cells);
    }

    /** @return array<string, string|int|float|null> */
    public function cells(): array
    {
        return $this->cells;
    }

    public function cell(string $columnId): string|int|float|null
    {
        if (!array_key_exists($columnId, $this->cells)) {
            throw new \OutOfBoundsException(sprintf('Table row has no cell for column "%s".', $columnId));
        }

        return $this->cells[$columnId];
    }
}
