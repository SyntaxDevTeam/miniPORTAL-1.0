<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Contract;

use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditEvent;

interface AuditSink
{
    public function record(AuditEvent $event): void;
}
