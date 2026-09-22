<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Model;

use JsonException;

final readonly class JobDefinition
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $packageId,
        public string $name,
        public array $payload = [],
        public ?string $idempotencyKey = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid package or job name.');
        }
        if ($idempotencyKey !== null && preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $idempotencyKey) !== 1) {
            throw new \InvalidArgumentException('Invalid idempotency key.');
        }
        self::validateData($payload, 0);
        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Job payload is not valid JSON data.', previous: $exception);
        }
        if (strlen($encoded) > 65536) {
            throw new \InvalidArgumentException('Job payload exceeds 64 KiB.');
        }
    }

    private static function validateData(mixed $value, int $depth): void
    {
        if ($depth > 8) {
            throw new \InvalidArgumentException('Job payload is too deeply nested.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::validateData($item, $depth + 1);
            }
            return;
        }
        if ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException('Job payload must contain data values only.');
        }
    }
}
