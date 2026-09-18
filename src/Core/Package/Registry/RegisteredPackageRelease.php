<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Registry;

use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;

final readonly class RegisteredPackageRelease
{
    public function __construct(
        public string $packageId,
        public string $version,
        public string $releasePath,
        public string $checksum,
        public PackageLifecycleState $state,
    ) {
        if ($packageId === '' || $version === '' || $releasePath === '' || $checksum === '') {
            throw new \InvalidArgumentException('Package release fields cannot be empty.');
        }
    }

    public function withState(PackageLifecycleState $state): self
    {
        return new self(
            $this->packageId,
            $this->version,
            $this->releasePath,
            $this->checksum,
            $state,
        );
    }
}
