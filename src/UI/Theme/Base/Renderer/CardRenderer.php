<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class CardRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string
    {
        if (!$component instanceof Card) {
            throw new \LogicException('CardRenderer received an unsupported component.');
        }
        $title = $component->title === null
            ? ''
            : '<h2 class="mp-card__title">' . Html::escape($component->title) . '</h2>';
        return '<section class="mp-card"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . $title
            . '<div class="mp-card__content">' . $registry->renderMany($component->children()) . '</div>'
            . '</section>';
    }
}
