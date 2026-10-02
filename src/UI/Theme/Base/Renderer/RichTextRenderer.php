<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\RichText;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;
use SyntaxDevTeam\MiniPortal\UI\Rendering\SafeRichText;

final class RichTextRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof RichText) {
            throw new \LogicException('RichTextRenderer received an unsupported component.');
        }
        return '<div class="mp-prose"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . (new SafeRichText())->render($component->content, $component->format) . '</div>';
    }
}
