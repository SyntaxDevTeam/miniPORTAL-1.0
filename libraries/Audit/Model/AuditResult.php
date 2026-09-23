<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Model;

enum AuditResult: string
{
    case Succeeded = 'succeeded';
    case Denied = 'denied';
    case Failed = 'failed';
}
