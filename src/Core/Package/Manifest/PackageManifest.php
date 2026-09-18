<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Manifest;

final readonly class PackageManifest
{
    /**
     * @param array<string, string> $requiredCapabilities
     * @param array<string, string> $requiredModules
     * @param array<string, string> $providedCapabilities
     */
    public function __construct(
        public int $schema,
        public string $id,
        public string $name,
        public string $version,
        public PackageType $type,
        public string $coreConstraint,
        public array $requiredCapabilities,
        public array $requiredModules,
        public array $providedCapabilities,
        public ?string $entrypoint,
    ) {
    }
}
