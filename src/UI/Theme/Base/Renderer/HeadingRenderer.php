<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class HeadingRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof Heading) {
            throw new \LogicException('HeadingRenderer received an unsupported component.');
        }
        return sprintf(
            '<h%d class="mp-heading"%s>%s</h%d>',
            $component->level,
            Html::identityAttribute(new ComponentRendererIdentity($component)),
            Html::escape($component->text),
            $component->level,
        );
    }
}
