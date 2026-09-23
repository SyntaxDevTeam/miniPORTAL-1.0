<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class Card implements Component
{
    /** @param list<Component> $content */
    public function __construct(
        private array $content,
        public ?string $title = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if ($content === [] || ($title !== null && trim($title) === '')) {
            throw new \InvalidArgumentException('Card requires content and a non-empty optional title.');
        }
    }

    public static function componentType(): string
    {
        return 'card';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return $this->content;
    }
}
