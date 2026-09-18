<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Filesystem\Model;

use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\ScopeViolation;

final readonly class Path
{
    private function __construct(public string $value)
    {
    }

    public static function root(): self
    {
        return new self('');
    }

    public static function fromString(string $path): self
    {
        if (str_contains($path, "\0") || str_contains($path, '\\')) {
            throw new ScopeViolation('Logical filesystem path contains forbidden characters.');
        }

        $trimmed = trim($path);
        if ($trimmed === '' || $trimmed === '/') {
            return self::root();
        }

        $segments = [];
        foreach (explode('/', trim($trimmed, '/')) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            if ($segment === '..') {
                throw new ScopeViolation('Parent path traversal is not allowed.');
            }

            if (preg_match('/[\x00-\x1F\x7F]/', $segment) === 1) {
                throw new ScopeViolation('Logical filesystem path contains control characters.');
            }

            $segments[] = $segment;
        }

        return new self(implode('/', $segments));
    }

    public function isRoot(): bool
    {
        return $this->value === '';
    }

    public function display(): string
    {
        return $this->isRoot() ? '/' : '/' . $this->value;
    }

    public function child(string $name): self
    {
        $child = self::fromString($name);
        if ($child->isRoot()) {
            throw new ScopeViolation('Child path cannot resolve to the scope root.');
        }

        return self::fromString(
            $this->isRoot() ? $child->value : $this->value . '/' . $child->value,
        );
    }
}
