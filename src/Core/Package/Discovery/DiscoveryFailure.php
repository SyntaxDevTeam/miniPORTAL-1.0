<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Discovery;

use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestValidationError;

final readonly class DiscoveryFailure
{
    /** @param list<ManifestValidationError> $errors */
    public function __construct(
        public string $directory,
        public array $errors,
    ) {
    }
}
