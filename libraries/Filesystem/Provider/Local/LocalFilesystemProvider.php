<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local;

use SyntaxDevTeam\MiniPortal\Library\Filesystem\Contract\ScopedFilesystem;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilePolicy;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilesystemRoot;

final class LocalFilesystemProvider
{
    public function scope(FilesystemRoot $root, FilePolicy $policy): ScopedFilesystem
    {
        return new LocalScopedFilesystem(
            new LocalPathResolver($root),
            $policy,
        );
    }
}
