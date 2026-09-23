<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme;

use SyntaxDevTeam\MiniPortal\UI\Contract\Theme;

final readonly class ResolvedTheme
{
    public function __construct(
        public Theme $theme,
        public bool $fallbackUsed,
        public ?string $fallbackReason = null,
    ) {
        if ($fallbackUsed === ($fallbackReason === null)) {
            throw new \InvalidArgumentException('Fallback resolution requires exactly one fallback reason.');
        }
    }
}
