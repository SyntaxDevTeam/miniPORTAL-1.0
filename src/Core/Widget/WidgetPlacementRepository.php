<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

interface WidgetPlacementRepository
{
    /** @return list<WidgetInstance> */
    public function forSlot(string $pageId, string $slot): array;

    public function save(WidgetInstance $instance): void;

    public function remove(string $id): void;
}
