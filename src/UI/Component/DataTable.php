<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\TableColumn;
use SyntaxDevTeam\MiniPortal\UI\Model\TableRow;

final readonly class DataTable implements Component
{
    /** @var list<TableRow> */
    private array $tableRows;

    /**
     * @param list<TableColumn> $columns
     * @param list<TableRow|array<string, string|int|float|null>> $rows
     */
    public function __construct(
        private array $columns,
        array $rows,
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

        $normalizedRows = [];
        foreach ($rows as $rowIndex => $row) {
            $tableRow = $row instanceof TableRow ? $row : TableRow::anonymous($row);
            $cells = $tableRow->cells();

            foreach ($columnIds as $columnId => $_present) {
                if (!array_key_exists($columnId, $cells)) {
                    throw new \InvalidArgumentException(sprintf(
                        'Table row %d is missing column "%s".',
                        $rowIndex,
                        $columnId,
                    ));
                }
            }
            foreach (array_keys($cells) as $cellId) {
                if (!isset($columnIds[$cellId])) {
                    throw new \InvalidArgumentException(sprintf(
                        'Table row %d contains unknown column "%s".',
                        $rowIndex,
                        $cellId,
                    ));
                }
            }

            $normalizedRows[] = $tableRow;
        }

        $this->tableRows = $normalizedRows;
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

    /**
     * Backward-compatible scalar row view.
     *
     * @return list<array<string, string|int|float|null>>
     */
    public function rows(): array
    {
        return array_map(
            static fn (TableRow $row): array => $row->cells(),
            $this->tableRows,
        );
    }

    /** @return list<TableRow> */
    public function tableRows(): array
    {
        return $this->tableRows;
    }
}
