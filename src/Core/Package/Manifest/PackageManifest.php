<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Manifest;

final readonly class PackageManifest
{
    /**
     * @param array<string, string> $capabilities
     * @param array<string, string> $modules
     */
    public function __construct(
        public int $schema,
        public string $id,
        public string $name,
        public string $version,
        public PackageType $type,
        public string $coreConstraint,
        public array $capabilities,
        public array $modules,
        public ?string $entrypoint,
    ) {
    }
}
