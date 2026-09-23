<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\TextTone;

final readonly class Text implements Component
{
    public function __construct(
        public string $content,
        public TextTone $tone = TextTone::Default,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if ($content === '') {
            throw new \InvalidArgumentException('Text content cannot be empty.');
        }
    }

    public static function componentType(): string
    {
        return 'text';
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
