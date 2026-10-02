<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI;

use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

/** Read-only UI rendering capability for modules. */
final readonly class UiFacade
{
    public function __construct(private ThemeResolver $themes)
    {
    }

    public function render(PageDefinition $page, string $themeId = 'plasma'): string
    {
        return $this->themes->resolve($themeId, $page->layoutRole)->theme->render($page);
    }

    /** @return list<string> */
    public function registeredThemeIds(): array
    {
        return $this->themes->registeredThemeIds();
    }
}
