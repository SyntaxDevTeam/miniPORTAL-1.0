<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

final class WidgetCatalog
{
    /** @var array<string, array<string, WidgetProvider>> */
    private array $providers = [];

    /** @param array<string, WidgetProvider> $providers */
    public function registerModule(string $moduleId, array $providers): void
    {
        if (isset($this->providers[$moduleId])) {
            throw new \LogicException('Widget module is already registered.');
        }
        $this->providers[$moduleId] = $providers;
    }

    public function unregisterModule(string $moduleId): void
    {
        unset($this->providers[$moduleId]);
    }

    public function provider(string $moduleId, string $type): ?WidgetProvider
    {
        return $this->providers[$moduleId][$type] ?? null;
    }
}
