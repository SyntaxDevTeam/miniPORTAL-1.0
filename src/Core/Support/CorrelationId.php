<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Support;

final readonly class CorrelationId
{
    private function __construct(public string $value)
    {
    }

    public static function generate(): self
    {
        return new self(bin2hex(random_bytes(12)));
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if (!preg_match('/^[a-z0-9][a-z0-9._-]{7,63}$/', $normalized)) {
            throw new \InvalidArgumentException('Invalid correlation ID.');
        }

        return new self($normalized);
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
