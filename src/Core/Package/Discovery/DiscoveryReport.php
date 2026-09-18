<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Discovery;

final readonly class DiscoveryReport
{
    /** @param list<DiscoveredPackage> $packages @param list<DiscoveryFailure> $failures */
    public function __construct(
        public array $packages,
        public array $failures,
    ) {
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }
}
