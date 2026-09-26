<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

enum AccountStatus: string
{
    case Active = 'active';
    case Pending = 'pending';
    case Blocked = 'blocked';
}
