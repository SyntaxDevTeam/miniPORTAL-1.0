<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Rendering;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;
use SyntaxDevTeam\MiniPortal\UI\Contract\UiRenderer;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;

final readonly class ThemeUiRenderer implements UiRenderer
{
    private RendererRegistry $renderers;

    public function __construct(private Theme $theme)
    {
        $this->renderers = $theme->renderers();
    }

    public function render(PageDefinition $page, string $language = 'pl'): string
    {
        return $this->theme->render($page, $language);
    }

    public function renderComponent(Component $component): string
    {
        return $this->renderers->render($component);
    }

    public function renderRegion(PageDefinition $page, string $region): string
    {
        return $this->renderers->renderMany($page->region($region));
    }
}
