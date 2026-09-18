<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

interface Module
{
    public function boot(ModuleContext $context): void;
}
