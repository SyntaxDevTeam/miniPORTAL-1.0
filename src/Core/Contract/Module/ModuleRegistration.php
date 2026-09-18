<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Core\Contract\Routing\RouteRegistrar;

interface ModuleRegistration
{
    public function routes(): RouteRegistrar;
}
