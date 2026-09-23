<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Fixtures\UI;

use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;

final readonly class FixtureTheme implements Theme
{
    /** @param list<string> $layouts */
    public function __construct(
        private string $themeId,
        private string $constraint,
        private array $layouts,
    ) {
    }

    public function id(): string
    {
        return $this->themeId;
    }

    public function uiApiConstraint(): string
    {
        return $this->constraint;
    }

    public function supportsLayout(string $layoutRole): bool
    {
        return in_array($layoutRole, $this->layouts, true);
    }

    public function renderers(): RendererRegistry
    {
        return (new BaseTheme())->renderers();
    }

    public function render(PageDefinition $page, string $language = 'pl'): string
    {
        return $this->themeId . ':' . $page->id . ':' . $language;
    }
}
