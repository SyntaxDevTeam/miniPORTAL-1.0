<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Manifest;

final readonly class ThemeManifest
{
    /**
     * @param list<string> $layouts
     * @param array<string, string> $assets
     */
    public function __construct(
        public int $schema,
        public string $id,
        public string $name,
        public string $version,
        public string $uiApiConstraint,
        public string $extends,
        public array $layouts,
        public array $assets,
        public ?string $preferredColorScheme,
    ) {
    }
}
