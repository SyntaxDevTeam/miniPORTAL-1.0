<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Provider;

use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditEvent;

final class InMemoryAuditSink implements AuditSink
{
    /** @var list<AuditEvent> */
    private array $events = [];

    public function record(AuditEvent $event): void
    {
        $this->events[] = $event;
    }

    /** @return list<AuditEvent> */
    public function events(): array
    {
        return $this->events;
    }
}
