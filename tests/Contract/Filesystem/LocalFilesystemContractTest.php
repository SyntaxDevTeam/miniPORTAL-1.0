<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Filesystem;

use SyntaxDevTeam\MiniPortal\Library\Filesystem\Contract\ScopedFilesystem;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilePolicy;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilesystemRoot;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local\LocalFilesystemProvider;

final class LocalFilesystemContractTest extends ScopedFilesystemContractTestCase
{
    private string $root;
    private ScopedFilesystem $filesystem;

    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'miniportal-fs-contract-'
            . bin2hex(random_bytes(8));

        if (!mkdir($this->root, 0770, true) && !is_dir($this->root)) {
            self::fail('Unable to create local filesystem contract fixture.');
        }

        $this->filesystem = (new LocalFilesystemProvider())->scope(
            new FilesystemRoot($this->root),
            FilePolicy::readWrite(),
        );
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
    }

    protected function filesystem(): ScopedFilesystem
    {
        return $this->filesystem;
    }

    private function removeTree(string $path): void
    {
        if (is_link($path) || is_file($path)) {
            @unlink($path);
            return;
        }

        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $this->removeTree($path . DIRECTORY_SEPARATOR . $entry);
        }

        @rmdir($path);
    }
}
