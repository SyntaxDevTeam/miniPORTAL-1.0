<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\UI\UiFacade;

/** Explicit public services available when constructing an active module. */
final readonly class ModuleServices
{
    public function __construct(
        public UiFacade $ui,
        public ?Database $database,
        public ?ModuleIdentity $identity,
    ) {
    }
}
