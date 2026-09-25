<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\DegradedState;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class DegradedStateRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string
    {
        if (!$component instanceof DegradedState) {
            throw new \LogicException('DegradedStateRenderer received an unsupported component.');
        }

        $content = $component->children() === []
            ? ''
            : '<div class="mp-degraded-state__available">' . $registry->renderMany($component->children()) . '</div>';

        return '<section class="mp-degraded-state" role="status" aria-live="polite"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . '<h2 class="mp-degraded-state__title">' . Html::escape($component->title) . '</h2>'
            . '<p class="mp-degraded-state__description">' . Html::escape($component->description) . '</p>'
            . $content . '</section>';
    }
}
