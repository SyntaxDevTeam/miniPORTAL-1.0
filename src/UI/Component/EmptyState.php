<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class EmptyState implements Component
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($title) === '' || ($description !== null && trim($description) === '')) {
            throw new \InvalidArgumentException('Empty state requires a title and a non-empty optional description.');
        }
    }

    public static function componentType(): string
    {
        return 'empty_state';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return [];
    }
}
