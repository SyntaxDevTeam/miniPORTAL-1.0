<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class EmptyStateRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof EmptyState) {
            throw new \LogicException('EmptyStateRenderer received an unsupported component.');
        }

        $description = $component->description === null
            ? ''
            : '<p class="mp-empty-state__description">' . Html::escape($component->description) . '</p>';

        return '<section class="mp-empty-state"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . '<h2 class="mp-empty-state__title">' . Html::escape($component->title) . '</h2>'
            . $description . '</section>';
    }
}
