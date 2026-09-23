<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class Stack implements Component
{
    /** @param list<Component> $items */
    public function __construct(
        private array $items,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if ($items === []) {
            throw new \InvalidArgumentException('Stack requires at least one component.');
        }
    }

    public static function componentType(): string
    {
        return 'stack';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return $this->items;
    }
}
