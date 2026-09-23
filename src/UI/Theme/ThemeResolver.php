<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme;

use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;

final class ThemeResolver
{
    /** @var array<string, Theme> */
    private array $themes = [];

    public function __construct(
        private readonly Theme $baseTheme,
        private readonly string $uiApiVersion,
        private readonly VersionConstraint $versions = new VersionConstraint(),
    ) {
        $this->themes[$baseTheme->id()] = $baseTheme;
    }

    public function register(Theme $theme): void
    {
        if (isset($this->themes[$theme->id()])) {
            throw new \LogicException(sprintf('Theme "%s" is already registered.', $theme->id()));
        }
        $this->themes[$theme->id()] = $theme;
    }

    public function resolve(string $activeThemeId, string $layoutRole): ResolvedTheme
    {
        $theme = $this->themes[$activeThemeId] ?? null;
        if ($theme === null) {
            return $this->fallback(sprintf('Theme "%s" is not registered.', $activeThemeId));
        }
        try {
            $compatible = $this->versions->matches($this->uiApiVersion, $theme->uiApiConstraint());
        } catch (\InvalidArgumentException) {
            $compatible = false;
        }
        if (!$compatible) {
            return $this->fallback(sprintf('Theme "%s" is incompatible with UI API %s.', $activeThemeId, $this->uiApiVersion));
        }
        if (!$theme->supportsLayout($layoutRole)) {
            return $this->fallback(sprintf('Theme "%s" does not support layout "%s".', $activeThemeId, $layoutRole));
        }
        return new ResolvedTheme($theme, false);
    }

    /** @return list<string> */
    public function registeredThemeIds(): array
    {
        return array_keys($this->themes);
    }

    private function fallback(string $reason): ResolvedTheme
    {
        return new ResolvedTheme($this->baseTheme, true, $reason);
    }
}
