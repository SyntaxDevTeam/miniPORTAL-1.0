<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Manifest;

final readonly class ManifestValidationError
{
    public function __construct(
        public string $path,
        public string $message,
    ) {
    }
}
