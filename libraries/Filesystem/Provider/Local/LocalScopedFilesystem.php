<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local;

use SyntaxDevTeam\MiniPortal\Library\Filesystem\Contract\ScopedFilesystem;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\AccessDenied;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\AlreadyExists;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\Conflict;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\IoFailure;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\QuotaExceeded;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\ScopeViolation;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\UnsupportedOperation;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FileMetadata;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilePolicy;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\Path;

final readonly class LocalScopedFilesystem implements ScopedFilesystem
{
    public function __construct(
        private LocalPathResolver $resolver,
        private FilePolicy $policy,
    ) {
    }

    public function exists(Path $path): bool
    {
        $this->assertReadAllowed();
        return $this->resolver->optionalExisting($path) !== null;
    }

    public function stat(Path $path): FileMetadata
    {
        $this->assertReadAllowed();
        $absolute = $this->resolver->existing($path);

        return $this->metadata($path, $absolute, false);
    }

    public function entries(Path $directory): array
    {
        $this->assertReadAllowed();
        $absolute = $this->resolver->existing($directory);

        if (!is_dir($absolute)) {
            throw new Conflict(sprintf(
                'Path is not a directory: %s',
                $directory->display(),
            ));
        }

        $names = @scandir($absolute);
        if ($names === false) {
            throw new IoFailure(sprintf(
                'Unable to list directory: %s',
                $directory->display(),
            ));
        }

        $entries = [];
        foreach ($names as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }

            $child = $directory->child($name);
            $candidate = $this->resolver->candidate($child);

            if (is_link($candidate)) {
                $entries[] = $this->metadata($child, $candidate, true);
                continue;
            }

            $entries[] = $this->metadata(
                $child,
                $this->resolver->existing($child),
                false,
            );
        }

        usort(
            $entries,
            static fn (FileMetadata $left, FileMetadata $right): int
                => strcmp($left->path->value, $right->path->value),
        );

        return $entries;
    }

    public function read(Path $path): string
    {
        $this->assertReadAllowed();
        $absolute = $this->resolver->existing($path);

        if (is_dir($absolute)) {
            throw new Conflict(sprintf('Cannot read directory as a file: %s', $path->display()));
        }

        $contents = @file_get_contents($absolute);
        if ($contents === false) {
            throw new IoFailure(sprintf('Unable to read file: %s', $path->display()));
        }

        return $contents;
    }

    public function write(Path $path, string $contents): void
    {
        $this->assertWriteAllowed();
        $this->assertWriteSize(strlen($contents));

        $existing = $this->resolver->optionalExisting($path);
        if ($existing === null) {
            $this->assertCreateAllowed();
        } elseif (is_dir($existing)) {
            throw new Conflict(sprintf('Cannot replace directory with file: %s', $path->display()));
        }

        $target = $this->resolver->target($path);
        $this->atomicWrite($target, $contents, $path);
    }

    public function createDirectory(Path $path): void
    {
        $this->assertCreateAllowed();

        if ($this->resolver->optionalExisting($path) !== null) {
            throw new AlreadyExists(sprintf('Path already exists: %s', $path->display()));
        }

        $target = $this->resolver->target($path);
        if (!@mkdir($target, 0770, false)) {
            throw new IoFailure(sprintf('Unable to create directory: %s', $path->display()));
        }
    }

    public function copy(Path $source, Path $target): void
    {
        $this->assertReadAllowed();
        $this->assertWriteAllowed();
        $this->assertCreateAllowed();

        $sourceAbsolute = $this->resolver->existing($source);
        if (is_dir($sourceAbsolute)) {
            throw new UnsupportedOperation('Directory copy is not supported by the baseline local provider.');
        }

        if ($this->resolver->optionalExisting($target) !== null) {
            throw new AlreadyExists(sprintf('Target already exists: %s', $target->display()));
        }

        $size = filesize($sourceAbsolute);
        if ($size === false) {
            throw new IoFailure(sprintf('Unable to determine source size: %s', $source->display()));
        }
        $this->assertWriteSize($size);

        $targetAbsolute = $this->resolver->target($target);
        $parent = dirname($targetAbsolute);
        $temporary = @tempnam($parent, '.miniportal-copy-');

        if ($temporary === false) {
            throw new IoFailure(sprintf('Unable to create temporary copy target for %s.', $target->display()));
        }

        try {
            if (!@copy($sourceAbsolute, $temporary)) {
                throw new IoFailure(sprintf('Unable to copy file to temporary target: %s', $target->display()));
            }

            if (!@rename($temporary, $targetAbsolute)) {
                throw new IoFailure(sprintf('Unable to atomically publish copied file: %s', $target->display()));
            }
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    public function move(Path $source, Path $target): void
    {
        $this->assertWriteAllowed();
        $this->assertCreateAllowed();
        $this->assertDeleteAllowed();

        if ($source->isRoot() || $target->isRoot()) {
            throw new ScopeViolation('The scope root cannot be moved or replaced.');
        }

        $sourceAbsolute = $this->resolver->existing($source);

        if ($this->resolver->optionalExisting($target) !== null) {
            throw new AlreadyExists(sprintf('Target already exists: %s', $target->display()));
        }

        $targetAbsolute = $this->resolver->target($target);
        if (!@rename($sourceAbsolute, $targetAbsolute)) {
            throw new IoFailure(sprintf(
                'Unable to move %s to %s.',
                $source->display(),
                $target->display(),
            ));
        }
    }

    public function delete(Path $path): void
    {
        $this->assertDeleteAllowed();

        if ($path->isRoot()) {
            throw new ScopeViolation('The filesystem scope root cannot be deleted.');
        }

        $absolute = $this->resolver->existing($path);

        if (is_dir($absolute)) {
            $entries = @scandir($absolute);
            if ($entries === false) {
                throw new IoFailure(sprintf('Unable to inspect directory: %s', $path->display()));
            }

            if (count($entries) > 2) {
                throw new Conflict(sprintf(
                    'Directory is not empty: %s',
                    $path->display(),
                ));
            }

            if (!@rmdir($absolute)) {
                throw new IoFailure(sprintf('Unable to delete directory: %s', $path->display()));
            }

            return;
        }

        if (!@unlink($absolute)) {
            throw new IoFailure(sprintf('Unable to delete file: %s', $path->display()));
        }
    }

    private function metadata(Path $path, string $absolute, bool $symlink): FileMetadata
    {
        $stat = @lstat($absolute);
        if ($stat === false) {
            throw new IoFailure(sprintf('Unable to read metadata: %s', $path->display()));
        }

        return new FileMetadata(
            path: $path,
            directory: !$symlink && is_dir($absolute),
            symlink: $symlink,
            size: (!$symlink && is_dir($absolute)) ? 0 : $stat['size'],
            modifiedAt: $stat['mtime'],
        );
    }

    private function atomicWrite(string $target, string $contents, Path $logicalPath): void
    {
        $parent = dirname($target);
        $temporary = @tempnam($parent, '.miniportal-write-');

        if ($temporary === false) {
            throw new IoFailure(sprintf(
                'Unable to create temporary file for %s.',
                $logicalPath->display(),
            ));
        }

        try {
            $written = @file_put_contents($temporary, $contents, LOCK_EX);
            if ($written === false || $written !== strlen($contents)) {
                throw new IoFailure(sprintf(
                    'Unable to write complete contents for %s.',
                    $logicalPath->display(),
                ));
            }

            if (!@rename($temporary, $target)) {
                throw new IoFailure(sprintf(
                    'Unable to atomically replace %s.',
                    $logicalPath->display(),
                ));
            }
        } finally {
            if (file_exists($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function assertReadAllowed(): void
    {
        if (!$this->policy->read) {
            throw new AccessDenied('Filesystem read operation is denied by scope policy.');
        }
    }

    private function assertWriteAllowed(): void
    {
        if (!$this->policy->write) {
            throw new AccessDenied('Filesystem write operation is denied by scope policy.');
        }
    }

    private function assertCreateAllowed(): void
    {
        if (!$this->policy->create) {
            throw new AccessDenied('Filesystem create operation is denied by scope policy.');
        }
    }

    private function assertDeleteAllowed(): void
    {
        if (!$this->policy->delete) {
            throw new AccessDenied('Filesystem delete operation is denied by scope policy.');
        }
    }

    private function assertWriteSize(int $bytes): void
    {
        if ($this->policy->maxWriteBytes !== null && $bytes > $this->policy->maxWriteBytes) {
            throw new QuotaExceeded(sprintf(
                'Write size %d exceeds scope limit %d.',
                $bytes,
                $this->policy->maxWriteBytes,
            ));
        }
    }
}
