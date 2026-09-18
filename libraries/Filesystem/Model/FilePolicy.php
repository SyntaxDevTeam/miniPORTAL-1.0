<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Model;

final readonly class FilePolicy
{
    public function __construct(
        public bool $read,
        public bool $write,
        public bool $create,
        public bool $delete,
        public ?int $maxWriteBytes = null,
    ) {
        if ($maxWriteBytes !== null && $maxWriteBytes < 0) {
            throw new \InvalidArgumentException('Maximum write size cannot be negative.');
        }
    }

    public static function readOnly(): self
    {
        return new self(
            read: true,
            write: false,
            create: false,
            delete: false,
        );
    }

    public static function readWrite(?int $maxWriteBytes = null): self
    {
        return new self(
            read: true,
            write: true,
            create: true,
            delete: true,
            maxWriteBytes: $maxWriteBytes,
        );
    }
}
