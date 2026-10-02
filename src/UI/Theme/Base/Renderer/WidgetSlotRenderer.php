<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\WidgetSlot;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class WidgetSlotRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string
    {
        if (!$component instanceof WidgetSlot) {
            throw new \LogicException('WidgetSlotRenderer received an unsupported component.');
        }
        return '<div class="mp-widget-slot" data-widget-slot="' . Html::escape($component->name) . '"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . $registry->renderMany($component->children()) . '</div>';
    }
}
