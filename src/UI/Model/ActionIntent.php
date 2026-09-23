<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

enum ActionIntent: string
{
    case Navigate = 'navigate';
    case Submit = 'submit';
    case Delete = 'delete';
    case OpenDialog = 'open_dialog';
    case Refresh = 'refresh';
    case StartJob = 'start_job';
}
