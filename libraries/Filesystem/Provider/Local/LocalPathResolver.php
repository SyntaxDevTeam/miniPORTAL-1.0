<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local;

use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\Conflict;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\IoFailure;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\PathNotFound;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\ProviderUnavailable;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\ScopeViolation;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\FilesystemRoot;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\Path;

final readonly class LocalPathResolver
{
    private string $root;

    public function __construct(FilesystemRoot $root)
    {
        if (is_link($root->value)) {
            throw new ScopeViolation('Local filesystem root cannot be a symbolic link.');
        }

        $canonical = realpath($root->value);
        if ($canonical === false || !is_dir($canonical)) {
            throw new ProviderUnavailable(sprintf(
                'Local filesystem root is not an accessible directory: %s',
                $root->value,
            ));
        }

        $this->root = rtrim($canonical, DIRECTORY_SEPARATOR);
        if ($this->root === '') {
            $this->root = DIRECTORY_SEPARATOR;
        }
    }

    public function root(): string
    {
        return $this->root;
    }

    public function candidate(Path $path): string
    {
        if ($path->isRoot()) {
            return $this->root;
        }

        return rtrim($this->root, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR
            . str_replace('/', DIRECTORY_SEPARATOR, $path->value);
    }

    public function existing(Path $path): string
    {
        $candidate = $this->candidate($path);
        $this->assertNoSymlinkComponents($path);

        if (!file_exists($candidate)) {
            throw new PathNotFound(sprintf('Path does not exist: %s', $path->display()));
        }

        $canonical = realpath($candidate);
        if ($canonical === false) {
            throw new IoFailure(sprintf('Unable to resolve path: %s', $path->display()));
        }

        $this->assertContained($canonical);

        return $canonical;
    }

    public function optionalExisting(Path $path): ?string
    {
        $candidate = $this->candidate($path);
        $this->assertNoSymlinkComponents($path);

        if (!file_exists($candidate)) {
            return null;
        }

        $canonical = realpath($candidate);
        if ($canonical === false) {
            throw new IoFailure(sprintf('Unable to resolve path: %s', $path->display()));
        }

        $this->assertContained($canonical);

        return $canonical;
    }

    public function target(Path $path): string
    {
        if ($path->isRoot()) {
            throw new ScopeViolation('The scope root cannot be used as a mutation target.');
        }

        $parent = $this->parent($path);
        $parentAbsolute = $this->existing($parent);

        if (!is_dir($parentAbsolute)) {
            throw new Conflict(sprintf(
                'Parent path is not a directory: %s',
                $parent->display(),
            ));
        }

        $candidate = $this->candidate($path);
        if (is_link($candidate)) {
            throw new ScopeViolation(sprintf(
                'Symbolic link access is not allowed: %s',
                $path->display(),
            ));
        }

        return $candidate;
    }

    private function parent(Path $path): Path
    {
        $parent = dirname($path->value);
        if ($parent === '.' || $parent === DIRECTORY_SEPARATOR) {
            return Path::root();
        }

        return Path::fromString(str_replace(DIRECTORY_SEPARATOR, '/', $parent));
    }

    private function assertNoSymlinkComponents(Path $path): void
    {
        if ($path->isRoot()) {
            return;
        }

        $current = $this->root;
        foreach (explode('/', $path->value) as $segment) {
            $current = rtrim($current, DIRECTORY_SEPARATOR)
                . DIRECTORY_SEPARATOR
                . $segment;

            if (is_link($current)) {
                throw new ScopeViolation(sprintf(
                    'Symbolic link traversal is not allowed: %s',
                    $path->display(),
                ));
            }

            if (!file_exists($current)) {
                break;
            }
        }
    }

    private function assertContained(string $canonical): void
    {
        if ($canonical === $this->root) {
            return;
        }

        $prefix = rtrim($this->root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($canonical, $prefix)) {
            throw new ScopeViolation('Resolved path escapes the configured filesystem scope.');
        }
    }
}
