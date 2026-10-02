<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\FixtureTest;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;

final class StatusModule implements Module
{
    public function register(ModuleRegistration $registration): void
    {
        $registration->routes->get('/status', 'status', static fn (Request $_): Response => Response::text('fixture ready'));
    }

    public function boot(ModuleContext $context): void
    {
    }
}
