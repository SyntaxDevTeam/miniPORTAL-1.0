<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class DegradedState implements Component
{
    /** @param list<Component> $availableContent */
    public function __construct(
        public string $title,
        public string $description,
        private array $availableContent = [],
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($title) === '' || trim($description) === '') {
            throw new \InvalidArgumentException('Degraded state requires a title and description.');
        }
    }

    public static function componentType(): string
    {
        return 'degraded_state';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return $this->availableContent;
    }
}
