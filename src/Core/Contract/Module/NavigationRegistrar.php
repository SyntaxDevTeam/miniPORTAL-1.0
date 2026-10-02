<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

interface NavigationRegistrar
{
    public function add(string $id, string $label, string $localPath = '/', string $area = 'public'): void;
}
