<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\SelectField;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class SelectFieldRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof SelectField) {
            throw new \LogicException('SelectFieldRenderer received an unsupported component.');
        }
        $options = '';
        foreach ($component->options as $value => $label) {
            $selected = $component->selected === $value ? ' selected' : '';
            $options .= '<option value="' . Html::escape($value) . '"' . $selected . '>' . Html::escape($label) . '</option>';
        }
        $required = $component->required ? ' required aria-required="true"' : '';
        return '<label class="mp-field"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '><span class="mp-field__label">'
            . Html::escape($component->label) . '</span><select name="' . Html::escape($component->name) . '"' . $required . '>' . $options . '</select></label>';
    }
}
