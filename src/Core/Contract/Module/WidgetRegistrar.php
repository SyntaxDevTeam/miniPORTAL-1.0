<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetProvider;

interface WidgetRegistrar
{
    public function register(string $type, WidgetProvider $provider): void;
}
