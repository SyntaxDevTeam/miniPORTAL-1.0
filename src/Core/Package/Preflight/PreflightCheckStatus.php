<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

enum PreflightCheckStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Skipped = 'skipped';
}
