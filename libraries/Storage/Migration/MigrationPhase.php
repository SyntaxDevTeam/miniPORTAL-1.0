<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

enum MigrationPhase: string
{
    case Expand = 'expand';
    case Transition = 'transition';
    case Contract = 'contract';
}
