<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class FormRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string
    {
        if (!$component instanceof Form) {
            throw new \LogicException('FormRenderer received an unsupported component.');
        }
        $csrf = $component->csrfToken === null ? '' : '<input type="hidden" name="_token" value="' . Html::escape($component->csrfToken) . '">';
        return '<form class="mp-form" action="' . Html::escape($component->action) . '" method="' . $component->method->value . '"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '><div class="mp-form__fields">'
            . $csrf . $registry->renderMany($component->children()) . '</div><button class="mp-form__submit" type="submit">'
            . Html::escape($component->submitLabel) . '</button></form>';
    }
}
