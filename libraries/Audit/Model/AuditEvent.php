<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Audit\Model;

use DateTimeImmutable;
use JsonException;

final readonly class AuditEvent
{
    /** @param array<string, mixed> $context */
    public function __construct(
        public string $id,
        public string $packageId,
        public string $actor,
        public string $action,
        public string $target,
        public AuditResult $result,
        public string $correlationId,
        public DateTimeImmutable $occurredAt,
        public array $context = [],
    ) {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1
            || preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1) {
            throw new \InvalidArgumentException('Invalid audit event or package ID.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:@-]{0,127}$/D', $actor) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $action) !== 1
            || preg_match('/^[a-z0-9][a-z0-9._-]{7,63}$/D', strtolower($correlationId)) !== 1) {
            throw new \InvalidArgumentException('Invalid audit actor, action or correlation ID.');
        }
        if ($target === '' || strlen($target) > 512 || preg_match('/[\x00-\x1F\x7F]/', $target) === 1) {
            throw new \InvalidArgumentException('Invalid audit target.');
        }

        self::validateContext($context, 0);
        try {
            $encoded = json_encode($context, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Audit context is not valid JSON data.', previous: $exception);
        }
        if (strlen($encoded) > 16384) {
            throw new \InvalidArgumentException('Audit context exceeds 16 KiB.');
        }
    }

    private static function validateContext(mixed $value, int $depth): void
    {
        if ($depth > 6) {
            throw new \InvalidArgumentException('Audit context is too deeply nested.');
        }
        if (is_array($value)) {
            foreach ($value as $key => $item) {
                if (is_string($key) && preg_match('/(?:password|passwd|secret|token|authorization|cookie|private.?key)/i', $key) === 1) {
                    throw new \InvalidArgumentException('Audit context contains a sensitive field name.');
                }
                self::validateContext($item, $depth + 1);
            }
            return;
        }
        if ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException('Audit context must contain data values only.');
        }
    }
}
