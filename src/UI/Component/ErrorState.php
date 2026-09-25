<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class ErrorState implements Component
{
    public function __construct(
        public string $title,
        public ?string $description = null,
        public ?string $errorId = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($title) === ''
            || ($description !== null && trim($description) === '')
            || ($errorId !== null && trim($errorId) === '')) {
            throw new \InvalidArgumentException('Error state values cannot be empty when present.');
        }
    }

    public static function componentType(): string
    {
        return 'error_state';
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
