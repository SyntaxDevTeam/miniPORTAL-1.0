<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\TableColumn;

final readonly class DataTable implements Component
{
    /**
     * @param list<TableColumn> $columns
     * @param list<array<string, string|int|float|null>> $rows
     */
    public function __construct(
        private array $columns,
        private array $rows,
        public ?string $caption = null,
        public ?EmptyState $emptyState = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if ($columns === []) {
            throw new \InvalidArgumentException('Data table requires at least one column.');
        }
        if ($caption !== null && trim($caption) === '') {
            throw new \InvalidArgumentException('Data table caption cannot be empty when present.');
        }
        if ($rows === [] && $emptyState === null) {
            throw new \InvalidArgumentException('Empty data tables require an explicit empty state.');
        }

        $columnIds = [];
        foreach ($columns as $column) {
            if (isset($columnIds[$column->id])) {
                throw new \InvalidArgumentException(sprintf('Duplicate table column ID: %s.', $column->id));
            }
            $columnIds[$column->id] = true;
        }

        foreach ($rows as $rowIndex => $row) {
            foreach ($columnIds as $columnId => $_present) {
                if (!array_key_exists($columnId, $row)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Table row %d is missing column "%s".',
                        $rowIndex,
                        $columnId,
                    ));
                }
            }
            foreach (array_keys($row) as $cellId) {
                if (!isset($columnIds[$cellId])) {
                    throw new \InvalidArgumentException(sprintf(
                        'Table row %d contains unknown column "%s".',
                        $rowIndex,
                        $cellId,
                    ));
                }
            }
        }
    }

    public static function componentType(): string
    {
        return 'data_table';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return $this->emptyState === null ? [] : [$this->emptyState];
    }

    /** @return list<TableColumn> */
    public function columns(): array
    {
        return $this->columns;
    }

    /** @return list<array<string, string|int|float|null>> */
    public function rows(): array
    {
        return $this->rows;
    }
}
