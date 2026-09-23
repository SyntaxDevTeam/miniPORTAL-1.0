<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

enum AlertSeverity: string
{
    case Info = 'info';
    case Success = 'success';
    case Warning = 'warning';
    case Error = 'error';
}
