<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;

final readonly class PackageRelease
{
    public function __construct(
        public PackageManifest $manifest,
        public string $releasePath,
        public PackageState $state,
    ) {
        if (trim($releasePath) === '') {
            throw new \InvalidArgumentException('Package release path cannot be empty.');
        }
    }

    public function withState(PackageState $state): self
    {
        return new self($this->manifest, $this->releasePath, $state);
    }
}
