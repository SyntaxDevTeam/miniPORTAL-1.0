<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class Heading implements Component
{
    public function __construct(
        public string $text,
        public int $level = 2,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($text) === '' || $level < 1 || $level > 6) {
            throw new \InvalidArgumentException('Heading requires text and a level between 1 and 6.');
        }
    }

    public static function componentType(): string
    {
        return 'heading';
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
