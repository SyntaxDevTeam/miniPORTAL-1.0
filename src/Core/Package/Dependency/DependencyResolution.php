<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Dependency;

final readonly class DependencyResolution
{
    /**
     * @param list<string> $loadOrder
     * @param array<string, string> $capabilityProviders
     * @param list<DependencyIssue> $issues
     */
    public function __construct(
        public array $loadOrder,
        public array $capabilityProviders,
        public array $issues,
    ) {
    }

    public function isSuccessful(): bool
    {
        return $this->issues === [];
    }
}
