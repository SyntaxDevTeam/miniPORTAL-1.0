<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

use SyntaxDevTeam\MiniPortal\Core\Http\RequestContext;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;

interface WidgetProvider
{
    /** @return list<Component> */
    public function render(WidgetInstance $instance, ?RequestContext $context): array;
}
