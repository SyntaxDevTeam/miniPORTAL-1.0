<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

final class InMemoryWidgetPlacementRepository implements WidgetPlacementRepository
{
    /** @var array<string, WidgetInstance> */
    private array $instances = [];

    public function forSlot(string $pageId, string $slot): array
    {
        $items = array_values(array_filter($this->instances,
            static fn (WidgetInstance $instance): bool => $instance->pageId === $pageId && $instance->slot === $slot));
        usort($items, static fn (WidgetInstance $a, WidgetInstance $b): int =>
            ($a->position <=> $b->position) ?: ($a->id <=> $b->id));
        return $items;
    }

    public function save(WidgetInstance $instance): void
    {
        $this->instances[$instance->id] = $instance;
    }

    public function remove(string $id): void
    {
        unset($this->instances[$id]);
    }
}
