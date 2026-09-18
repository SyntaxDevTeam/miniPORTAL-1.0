<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Contract;

use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FileMetadata;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\Path;

interface ScopedFilesystem
{
    public function exists(Path $path): bool;

    public function stat(Path $path): FileMetadata;

    /** @return list<FileMetadata> */
    public function entries(Path $directory): array;

    public function read(Path $path): string;

    public function write(Path $path, string $contents): void;

    public function createDirectory(Path $path): void;

    public function copy(Path $source, Path $target): void;

    public function move(Path $source, Path $target): void;

    public function delete(Path $path): void;
}
