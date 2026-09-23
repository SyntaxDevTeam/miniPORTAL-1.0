<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base;

use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\AlertRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\CardRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\HeadingRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\StackRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\TextRenderer;

final class BaseTheme
{
    public function renderers(): RendererRegistry
    {
        $registry = new RendererRegistry();
        $registry->register(Text::class, new TextRenderer());
        $registry->register(Heading::class, new HeadingRenderer());
        $registry->register(Alert::class, new AlertRenderer());
        $registry->register(Stack::class, new StackRenderer());
        $registry->register(Card::class, new CardRenderer());
        return $registry;
    }
}
