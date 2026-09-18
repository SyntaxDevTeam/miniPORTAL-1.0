<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module\Registration;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Contract\Routing\RouteRegistrar;

final readonly class DefaultModuleRegistration implements ModuleRegistration
{
    public function __construct(private RouteRegistrar $routes)
    {
    }

    public function routes(): RouteRegistrar
    {
        return $this->routes;
    }
}
