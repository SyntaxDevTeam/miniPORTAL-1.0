<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base;

use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\AlertRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\CardRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\HeadingRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\StackRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer\TextRenderer;

final class BaseTheme implements Theme
{
    public function id(): string
    {
        return 'base';
    }

    public function uiApiConstraint(): string
    {
        return '^1.0';
    }

    public function supportsLayout(string $_layoutRole): bool
    {
        return true;
    }

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

    public function render(PageDefinition $page, string $language = 'pl'): string
    {
        return (new BasePageRenderer($this->renderers()))->render($page, $language);
    }
}
