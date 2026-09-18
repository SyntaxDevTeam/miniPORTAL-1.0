<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Model;

final readonly class FileMetadata
{
    public function __construct(
        public Path $path,
        public bool $directory,
        public bool $symlink,
        public int $size,
        public int $modifiedAt,
    ) {
        if ($size < 0 || $modifiedAt < 0) {
            throw new \InvalidArgumentException('Filesystem metadata values cannot be negative.');
        }
    }
}
