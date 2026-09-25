<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\LoadingState;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class LoadingStateRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof LoadingState) {
            throw new \LogicException('LoadingStateRenderer received an unsupported component.');
        }

        return '<div class="mp-loading-state" role="status" aria-live="polite" aria-busy="true"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . '<span class="mp-loading-state__indicator" aria-hidden="true"></span>'
            . '<span class="mp-loading-state__label">' . Html::escape($component->label) . '</span></div>';
    }
}
