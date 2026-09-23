<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Configuration;

use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;

final readonly class ApplicationConfig
{
    public function __construct(
        public ApplicationEnvironment $environment,
        public ?DatabaseSettings $database = null,
    ) {
    }
}
