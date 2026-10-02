<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

/** Explicit public services available when constructing an active module. */
final readonly class ModuleServices
{
    public function __construct(
        public ThemeResolver $themes,
        public ?Database $database,
        public ?AuthenticationManager $authentication,
    ) {
    }
}
