<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Filesystem;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\AccessDenied;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\QuotaExceeded;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\ScopeViolation;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilePolicy;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilesystemRoot;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\Path;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local\LocalFilesystemProvider;

final class LocalFilesystemSecurityTest extends TestCase
{
    private string $root;
    private string $outside;

    protected function setUp(): void
    {
        $suffix = bin2hex(random_bytes(8));
        $this->root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'miniportal-fs-root-' . $suffix;
        $this->outside = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'miniportal-fs-outside-' . $suffix;

        foreach ([$this->root, $this->outside] as $directory) {
            if (!mkdir($directory, 0770, true) && !is_dir($directory)) {
                self::fail('Unable to create local filesystem security fixture.');
            }
        }
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->root);
        $this->removeTree($this->outside);
    }

    public function testParentTraversalIsRejectedBeforeProviderAccess(): void
    {
        $this->expectException(ScopeViolation::class);

        Path::fromString('/plugins/../../outside');
    }

    public function testBackslashPathEscapeIsRejected(): void
    {
        $this->expectException(ScopeViolation::class);

        Path::fromString('..\\outside');
    }

    public function testSymlinkEscapeIsRejected(): void
    {
        file_put_contents($this->outside . DIRECTORY_SEPARATOR . 'secret.txt', 'secret');

        $link = $this->root . DIRECTORY_SEPARATOR . 'escape';
        if (!@symlink($this->outside, $link)) {
            self::markTestSkipped('Symlink creation is unavailable in this environment.');
        }

        $filesystem = (new LocalFilesystemProvider())->scope(
            new FilesystemRoot($this->root),
            FilePolicy::readWrite(),
        );

        $this->expectException(ScopeViolation::class);

        $filesystem->read(Path::fromString('/escape/secret.txt'));
    }

    public function testReadOnlyPolicyRejectsWrite(): void
    {
        $filesystem = (new LocalFilesystemProvider())->scope(
            new FilesystemRoot($this->root),
            FilePolicy::readOnly(),
        );

        $this->expectException(AccessDenied::class);

        $filesystem->write(Path::fromString('/blocked.txt'), 'value');
    }

    public function testWriteLimitIsEnforced(): void
    {
        $filesystem = (new LocalFilesystemProvider())->scope(
            new FilesystemRoot($this->root),
            FilePolicy::readWrite(maxWriteBytes: 4),
        );

        $this->expectException(QuotaExceeded::class);

        $filesystem->write(Path::fromString('/too-large.txt'), '12345');
    }

    public function testAtomicWriteLeavesNoTemporaryArtifacts(): void
    {
        $filesystem = (new LocalFilesystemProvider())->scope(
            new FilesystemRoot($this->root),
            FilePolicy::readWrite(),
        );

        $filesystem->write(Path::fromString('/config.txt'), 'first');
        $filesystem->write(Path::fromString('/config.txt'), 'second');

        self::assertSame('second', $filesystem->read(Path::fromString('/config.txt')));

        $entries = scandir($this->root);
        self::assertNotFalse($entries);

        $temporary = array_filter(
            $entries,
            static fn (string $entry): bool => str_starts_with($entry, '.miniportal-write-'),
        );

        self::assertSame([], array_values($temporary));
    }

    public function testSymlinkIsVisibleAsMetadataButCannotBeDereferenced(): void
    {
        file_put_contents($this->outside . DIRECTORY_SEPARATOR . 'target.txt', 'target');

        $link = $this->root . DIRECTORY_SEPARATOR . 'visible-link';
        if (!@symlink($this->outside . DIRECTORY_SEPARATOR . 'target.txt', $link)) {
            self::markTestSkipped('Symlink creation is unavailable in this environment.');
        }

        $filesystem = (new LocalFilesystemProvider())->scope(
            new FilesystemRoot($this->root),
            FilePolicy::readWrite(),
        );

        $entries = $filesystem->entries(Path::root());

        self::assertCount(1, $entries);
        self::assertTrue($entries[0]->symlink);

        $this->expectException(ScopeViolation::class);
        $filesystem->read(Path::fromString('/visible-link'));
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
