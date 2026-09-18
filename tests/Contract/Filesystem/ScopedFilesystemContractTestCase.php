<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Filesystem;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Contract\ScopedFilesystem;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\Conflict;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\Path;

abstract class ScopedFilesystemContractTestCase extends TestCase
{
    abstract protected function filesystem(): ScopedFilesystem;

    public function testWriteReadStatAndReplaceLifecycle(): void
    {
        $filesystem = $this->filesystem();
        $path = Path::fromString('/config/app.txt');

        $filesystem->createDirectory(Path::fromString('/config'));
        $filesystem->write($path, 'first');

        self::assertTrue($filesystem->exists($path));
        self::assertSame('first', $filesystem->read($path));

        $metadata = $filesystem->stat($path);
        self::assertFalse($metadata->directory);
        self::assertFalse($metadata->symlink);
        self::assertSame(5, $metadata->size);
        self::assertSame('config/app.txt', $metadata->path->value);

        $filesystem->write($path, 'second');
        self::assertSame('second', $filesystem->read($path));
    }

    public function testDirectoryEntriesAreScopedAndDeterministic(): void
    {
        $filesystem = $this->filesystem();

        $filesystem->createDirectory(Path::fromString('/data'));
        $filesystem->write(Path::fromString('/data/b.txt'), 'b');
        $filesystem->write(Path::fromString('/data/a.txt'), 'a');

        $entries = $filesystem->entries(Path::fromString('/data'));

        self::assertSame(
            ['data/a.txt', 'data/b.txt'],
            array_map(
                static fn ($entry): string => $entry->path->value,
                $entries,
            ),
        );
    }

    public function testCopyMoveAndDeleteLifecycle(): void
    {
        $filesystem = $this->filesystem();

        $filesystem->write(Path::fromString('/source.txt'), 'payload');
        $filesystem->copy(
            Path::fromString('/source.txt'),
            Path::fromString('/copy.txt'),
        );

        self::assertSame('payload', $filesystem->read(Path::fromString('/copy.txt')));

        $filesystem->move(
            Path::fromString('/copy.txt'),
            Path::fromString('/moved.txt'),
        );

        self::assertFalse($filesystem->exists(Path::fromString('/copy.txt')));
        self::assertSame('payload', $filesystem->read(Path::fromString('/moved.txt')));

        $filesystem->delete(Path::fromString('/moved.txt'));
        self::assertFalse($filesystem->exists(Path::fromString('/moved.txt')));
    }

    public function testNonEmptyDirectoryDeletionIsRejected(): void
    {
        $filesystem = $this->filesystem();

        $filesystem->createDirectory(Path::fromString('/non-empty'));
        $filesystem->write(Path::fromString('/non-empty/file.txt'), 'value');

        $this->expectException(Conflict::class);

        $filesystem->delete(Path::fromString('/non-empty'));
    }
}
