<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\TextField;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class TextFieldRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof TextField) {
            throw new \LogicException('TextFieldRenderer received an unsupported component.');
        }
        $value = $component->value === null ? '' : ' value="' . Html::escape($component->value) . '"';
        $required = $component->required ? ' required aria-required="true"' : '';
        $invalid = $component->error === null ? '' : ' aria-invalid="true"';
        $help = $component->help === null ? '' : '<small class="mp-field__help">' . Html::escape($component->help) . '</small>';
        $error = $component->error === null ? '' : '<small class="mp-field__error" role="alert">' . Html::escape($component->error) . '</small>';
        return '<label class="mp-field"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '><span class="mp-field__label">'
            . Html::escape($component->label) . '</span><input name="' . Html::escape($component->name) . '" type="' . $component->type->value . '"'
            . $value . $required . $invalid . '>' . $help . $error . '</label>';
    }
}
