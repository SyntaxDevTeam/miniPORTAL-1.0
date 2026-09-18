<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Discovery;

use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;

final readonly class DiscoveredPackage
{
    public function __construct(
        public string $directory,
        public PackageManifest $manifest,
    ) {
    }
}
