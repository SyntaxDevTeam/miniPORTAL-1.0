<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\DataTable;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class DataTableRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string
    {
        if (!$component instanceof DataTable) {
            throw new \LogicException('DataTableRenderer received an unsupported component.');
        }

        $identity = Html::identityAttribute(new ComponentRendererIdentity($component));
        $controls = $component->queryControls === null ? '' : $registry->render($component->queryControls);
        $pagination = $component->pagination === null ? '' : $registry->render($component->pagination);
        if ($component->tableRows() === []) {
            if ($component->emptyState === null) {
                throw new \LogicException('Empty data table has no empty state.');
            }

            return '<div class="mp-data-table mp-data-table--empty"' . $identity . '>'
                . $controls . $registry->render($component->emptyState) . $pagination . '</div>';
        }

        $caption = $component->caption === null
            ? ''
            : '<caption>' . Html::escape($component->caption) . '</caption>';

        $head = '';
        foreach ($component->columns() as $column) {
            $class = $column->numeric ? ' class="mp-data-table__numeric"' : '';
            $ariaSort = $column->sortDirection === null ? '' : ' aria-sort="' . $column->sortDirection->value . '"';
            $label = Html::escape($column->label);
            if ($column->sortUrl !== null) {
                $indicator = match ($column->sortDirection) {
                    \SyntaxDevTeam\MiniPortal\UI\Model\SortDirection::Ascending => ' <span aria-hidden="true">↑</span>',
                    \SyntaxDevTeam\MiniPortal\UI\Model\SortDirection::Descending => ' <span aria-hidden="true">↓</span>',
                    null => '',
                };
                $label = '<a class="mp-data-table__sort" href="' . Html::escape($column->sortUrl) . '">' . $label . $indicator . '</a>';
            }
            $head .= '<th scope="col"' . $class . $ariaSort . '>' . $label . '</th>';
        }

        $body = '';
        foreach ($component->tableRows() as $row) {
            $cells = '';
            foreach ($component->columns() as $column) {
                $value = $row->cell($column->id);
                $class = $column->numeric ? ' class="mp-data-table__numeric"' : '';
                $content = $value === null
                    ? '<span class="mp-data-table__missing" aria-label="Brak danych">—</span>'
                    : Html::escape((string) $value);
                $cells .= '<td' . $class . '>' . $content . '</td>';
            }
            $rowIdentity = $row->id === null ? '' : ' data-row-id="' . Html::escape($row->id) . '"';
            $body .= '<tr' . $rowIdentity . '>' . $cells . '</tr>';
        }

        return '<div class="mp-data-table"' . $identity . '>' . $controls . '<table>'
            . $caption . '<thead><tr>' . $head . '</tr></thead><tbody>' . $body . '</tbody></table>' . $pagination . '</div>';
    }
}
