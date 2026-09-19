<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

enum ExpectedLock: string
{
    case None = 'none';
    case Brief = 'brief';
    case Extended = 'extended';
}
