<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class LoadingState implements Component
{
    public function __construct(
        public string $label = 'Ładowanie…',
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Loading state label cannot be empty.');
        }
    }

    public static function componentType(): string
    {
        return 'loading_state';
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
