<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Model;

final readonly class StorageNamespace
{
    private string $prefix;

    public function __construct(public string $ownerId)
    {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/', $ownerId) !== 1) {
            throw new \InvalidArgumentException('Storage owner ID must be a valid package identifier.');
        }

        $this->prefix = 'pkg_' . substr(hash('sha256', $ownerId), 0, 12);
    }

    public function table(string $localName): SqlIdentifier
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,43}$/', $localName) !== 1) {
            throw new \InvalidArgumentException(
                'Storage table name must use lowercase letters, digits and underscores and be at most 44 characters.',
            );
        }

        return new SqlIdentifier($this->prefix . '_' . $localName);
    }
}
