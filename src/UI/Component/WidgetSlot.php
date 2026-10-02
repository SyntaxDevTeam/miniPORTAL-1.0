<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

/** Named composition point; may be placed inside any component accepting children. */
final readonly class WidgetSlot implements Component
{
    /** @param list<Component> $items */
    public function __construct(public string $name, private array $items = [], private ?ComponentIdentity $componentIdentity = null)
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Widget slot name is invalid.');
        }
    }

    public static function componentType(): string
    {
        return 'widget_slot';
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
