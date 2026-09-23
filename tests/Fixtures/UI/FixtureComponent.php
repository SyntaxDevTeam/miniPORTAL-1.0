<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Fixtures\UI;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final class FixtureComponent implements Component
{
    /** @param list<Component> $children */
    public function __construct(
        private readonly ?ComponentIdentity $identity = null,
        private array $children = [],
    ) {
    }

    public static function componentType(): string
    {
        return 'fixture';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->identity;
    }

    public function children(): array
    {
        return $this->children;
    }

    /** Test-only mutation used to prove cycle rejection. */
    public function append(self $component): void
    {
        $this->children[] = $component;
    }
}
