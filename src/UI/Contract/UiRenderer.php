<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Contract;

use SyntaxDevTeam\MiniPortal\UI\PageDefinition;

interface UiRenderer extends PageRenderer
{
    public function renderComponent(Component $component): string;

    public function renderRegion(PageDefinition $page, string $region): string;
}
