<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Plasma;

use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;

final class PlasmaTheme implements Theme
{
    private readonly RendererRegistry $renderers;

    public function __construct(
        private readonly string $assetBaseUrl = '/assets/themes/plasma',
    ) {
        // Plasma currently inherits every component renderer from the mandatory
        // Base Theme. Component-specific overrides can be registered here later.
        $this->renderers = (new BaseTheme())->renderers();
    }

    public function renderers(): RendererRegistry
    {
        return $this->renderers;
    }

    public function id(): string
    {
        return 'plasma';
    }

    public function uiApiConstraint(): string
    {
        return '^1.0';
    }

    public function supportsLayout(string $layoutRole): bool
    {
        return in_array($layoutRole, ['public', 'application', 'dashboard'], true);
    }

    public function render(PageDefinition $page, string $language = 'pl'): string
    {
        return (new PlasmaPageRenderer($this->renderers, $this->assetBaseUrl))->render($page, $language);
    }
}
