<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Contract;

use SyntaxDevTeam\MiniPortal\UI\PageDefinition;

interface PageRenderer
{
    public function render(PageDefinition $page, string $language = 'pl'): string;
}
