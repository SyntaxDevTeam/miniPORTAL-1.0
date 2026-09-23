<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Realtime\Model;

use JsonException;

final readonly class RealtimeEvent
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $id,
        public string $packageId,
        public string $channel,
        public string $name,
        public array $payload = [],
    ) {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new \InvalidArgumentException('Realtime event ID must be a 128-bit lowercase hexadecimal value.');
        }
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1) {
            throw new \InvalidArgumentException('Invalid realtime package ID.');
        }
        if (preg_match('/^[a-z][a-z0-9_.:-]{0,127}$/D', $channel) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid realtime channel or event name.');
        }
        self::validateData($payload, 0);
        try {
            $encoded = json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \InvalidArgumentException('Realtime payload is not valid JSON data.', previous: $exception);
        }
        if (strlen($encoded) > 65536) {
            throw new \InvalidArgumentException('Realtime payload exceeds 64 KiB.');
        }
    }

    private static function validateData(mixed $value, int $depth): void
    {
        if ($depth > 8) {
            throw new \InvalidArgumentException('Realtime payload is too deeply nested.');
        }
        if (is_array($value)) {
            foreach ($value as $item) {
                self::validateData($item, $depth + 1);
            }
            return;
        }
        if ($value !== null && !is_scalar($value)) {
            throw new \InvalidArgumentException('Realtime payload must contain data values only.');
        }
    }
}
