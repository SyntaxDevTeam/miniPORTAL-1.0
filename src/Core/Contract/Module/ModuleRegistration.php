<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

final readonly class ModuleRegistration
{
    public function __construct(public RouteRegistrar $routes, public ?WidgetRegistrar $widgets = null, public ?ApiRegistrar $api = null)
    {
    }
}
