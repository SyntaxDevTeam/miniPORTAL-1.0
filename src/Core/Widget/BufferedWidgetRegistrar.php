<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\WidgetRegistrar;

final class BufferedWidgetRegistrar implements WidgetRegistrar
{
    /** @var array<string, WidgetProvider> */
    private array $providers = [];

    public function register(string $type, WidgetProvider $provider): void
    {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $type) !== 1 || isset($this->providers[$type])) {
            throw new \InvalidArgumentException('Widget type is invalid or duplicated.');
        }
        $this->providers[$type] = $provider;
    }

    /** @return array<string, WidgetProvider> */
    public function providers(): array
    {
        return $this->providers;
    }
}
