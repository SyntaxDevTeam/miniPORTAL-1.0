<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPreflightResult;

interface MigrationPreflightCheck
{
    public function id(): string;

    public function check(Database $database, MigrationDefinition $migration): MigrationPreflightResult;
}
