<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;

/** Optional, append-only migration catalog owned by a module. */
interface ModuleMigrationProvider
{
    /** @return list<MigrationDefinition> Oldest first, including already applied definitions. */
    public static function migrations(): array;
}
