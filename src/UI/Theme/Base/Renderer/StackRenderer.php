<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class StackRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string
    {
        if (!$component instanceof Stack) {
            throw new \LogicException('StackRenderer received an unsupported component.');
        }
        return '<div class="mp-stack"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . $registry->renderMany($component->children())
            . '</div>';
    }
}
