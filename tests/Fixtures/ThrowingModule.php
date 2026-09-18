<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Fixtures;

use RuntimeException;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;

final class ThrowingModule implements Module
{
    public function register(ModuleRegistration $registration): void
    {
    }

    public function boot(ModuleContext $context): void
    {
        throw new RuntimeException('fixture module exploded');
    }
}
