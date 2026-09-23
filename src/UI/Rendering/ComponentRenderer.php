<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Rendering;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;

interface ComponentRenderer
{
    public function render(Component $component, RendererRegistry $registry): string;
}
