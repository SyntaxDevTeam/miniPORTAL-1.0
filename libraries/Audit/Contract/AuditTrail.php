<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Contract;

use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditEvent;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;

interface AuditTrail
{
    /** @param array<string, mixed> $context */
    public function record(
        string $actor,
        string $action,
        string $target,
        AuditResult $result,
        string $correlationId,
        array $context = [],
    ): AuditEvent;
}
