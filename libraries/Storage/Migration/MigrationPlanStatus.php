<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

enum MigrationPlanStatus: string
{
    case Pending = 'pending';
    case Applied = 'applied';
    case ChecksumMismatch = 'checksum_mismatch';
}
