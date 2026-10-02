<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

/** Optional factory for modules that require host-provided public services. */
interface ModuleFactory
{
    public static function create(ModuleServices $services): Module;
}
