<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

enum ModuleExecutionPhase: string
{
    case Registration = 'registration';
    case Boot = 'boot';
}
