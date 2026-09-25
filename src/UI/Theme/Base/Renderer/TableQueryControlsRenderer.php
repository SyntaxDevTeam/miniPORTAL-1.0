<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\TableQueryControls;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class TableQueryControlsRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof TableQueryControls) {
            throw new \LogicException('TableQueryControlsRenderer received an unsupported component.');
        }

        $fields = '';
        if ($component->searchParameter !== null) {
            $value = $component->searchValue === null ? '' : ' value="' . Html::escape($component->searchValue) . '"';
            $fields .= '<label class="mp-table-query__search"><span>' . Html::escape($component->searchLabel)
                . '</span><input type="search" name="' . Html::escape($component->searchParameter) . '"' . $value . '></label>';
        }

        foreach ($component->filters() as $filter) {
            $options = '';
            foreach ($filter->options as $value => $label) {
                $selected = $filter->selected === $value ? ' selected' : '';
                $options .= '<option value="' . Html::escape($value) . '"' . $selected . '>'
                    . Html::escape($label) . '</option>';
            }
            $fields .= '<label class="mp-table-query__filter"><span>' . Html::escape($filter->label)
                . '</span><select name="' . Html::escape($filter->name) . '">' . $options . '</select></label>';
        }

        foreach ($component->preservedParameters() as $name => $value) {
            $fields .= '<input type="hidden" name="' . Html::escape($name) . '" value="' . Html::escape($value) . '">';
        }

        return '<form class="mp-table-query" method="get" action="' . Html::escape($component->action) . '" role="search"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . $fields . '<button type="submit">Zastosuj</button></form>';
    }
}
