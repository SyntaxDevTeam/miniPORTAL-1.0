<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\CheckboxField;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class CheckboxFieldRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof CheckboxField) {
            throw new \LogicException('CheckboxFieldRenderer received an unsupported component.');
        }
        $checked = $component->checked ? ' checked' : '';
        $required = $component->required ? ' required aria-required="true"' : '';
        return '<label class="mp-field mp-field--checkbox"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '><input name="'
            . Html::escape($component->name) . '" type="checkbox" value="1"' . $checked . $required . '><span>' . Html::escape($component->label) . '</span></label>';
    }
}
