<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\TextAreaField;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class TextAreaFieldRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof TextAreaField) {
            throw new \LogicException('TextAreaFieldRenderer received an unsupported component.');
        }
        $required = $component->required ? ' required aria-required="true"' : '';
        $invalid = $component->error === null ? '' : ' aria-invalid="true"';
        $help = $component->help === null ? '' : '<small class="mp-field__help">' . Html::escape($component->help) . '</small>';
        $error = $component->error === null ? '' : '<small class="mp-field__error" role="alert">' . Html::escape($component->error) . '</small>';
        return '<label class="mp-field"' . Html::identityAttribute(new ComponentRendererIdentity($component))
            . '><span class="mp-field__label">' . Html::escape($component->label)
            . '</span><textarea name="' . Html::escape($component->name) . '" rows="' . $component->rows . '"'
            . $required . $invalid . '>' . Html::escape($component->value ?? '') . '</textarea>' . $help . $error . '</label>';
    }
}
