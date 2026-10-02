<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Navigation;

final readonly class NavigationItem
{
    public function __construct(
        public string $id,
        public string $label,
        public string $url,
        public string $area,
    ) {
    }
}
