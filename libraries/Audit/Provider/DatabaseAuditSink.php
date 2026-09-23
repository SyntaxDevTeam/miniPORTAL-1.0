<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Provider;

use JsonException;
use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditEvent;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class DatabaseAuditSink implements AuditSink
{
    private string $table;

    public function __construct(private Database $database)
    {
        $this->table = (new StorageNamespace(DatabaseAuditSinkMigration::OWNER_ID))->table('events')->value;
    }

    public function record(AuditEvent $event): void
    {
        try {
            $context = json_encode($event->context, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Audit context cannot be encoded.', previous: $exception);
        }

        $this->database->execute(new SqlStatement(
            sprintf(
                'INSERT INTO %s (id, package_id, actor, action, target, result, correlation_id, occurred_at, context_json) '
                . 'VALUES (:id, :package_id, :actor, :action, :target, :result, :correlation_id, :occurred_at, :context_json)',
                $this->table,
            ),
            [
                'id' => $event->id,
                'package_id' => $event->packageId,
                'actor' => $event->actor,
                'action' => $event->action,
                'target' => $event->target,
                'result' => $event->result->value,
                'correlation_id' => $event->correlationId,
                'occurred_at' => $event->occurredAt->format('Y-m-d\TH:i:s.uP'),
                'context_json' => $context,
            ],
        ));
    }
}
