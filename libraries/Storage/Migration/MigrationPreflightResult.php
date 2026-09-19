<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Migration;

final readonly class MigrationPreflightResult
{
    public function __construct(
        public string $checkId,
        public bool $passed,
        public string $message,
    ) {
        if (trim($checkId) === '') {
            throw new \InvalidArgumentException('Migration preflight check ID cannot be empty.');
        }
    }

    public static function passed(string $checkId, string $message): self
    {
        return new self($checkId, true, $message);
    }

    public static function failed(string $checkId, string $message): self
    {
        return new self($checkId, false, $message);
    }
}
