<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Provider;

use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditEvent;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;

final readonly class ScopedAuditTrail implements AuditTrail
{
    public function __construct(
        private string $packageId,
        private AuditSink $sink,
        private Clock $clock,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1) {
            throw new \InvalidArgumentException('Invalid audit package ID.');
        }
    }

    public function record(
        string $actor,
        string $action,
        string $target,
        AuditResult $result,
        string $correlationId,
        array $context = [],
    ): AuditEvent {
        $event = new AuditEvent(
            bin2hex(random_bytes(16)),
            $this->packageId,
            $actor,
            $action,
            $target,
            $result,
            strtolower($correlationId),
            $this->clock->now(),
            $context,
        );
        $this->sink->record($event);
        return $event;
    }
}
