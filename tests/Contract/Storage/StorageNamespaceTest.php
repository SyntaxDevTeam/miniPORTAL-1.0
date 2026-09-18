<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Storage;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlIdentifier;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class StorageNamespaceTest extends TestCase
{
    public function testPackageNamespaceProducesStableSafeTableIdentifier(): void
    {
        $first = new StorageNamespace('minecraft.files');
        $second = new StorageNamespace('minecraft.files');

        self::assertSame(
            $first->table('settings')->value,
            $second->table('settings')->value,
        );
        self::assertMatchesRegularExpression(
            '/^pkg_[a-f0-9]{12}_settings$/',
            $first->table('settings')->value,
        );
    }

    public function testDifferentPackageIdsDoNotShareTablePrefix(): void
    {
        $first = new StorageNamespace('package.one');
        $second = new StorageNamespace('package-two');

        self::assertNotSame(
            $first->table('state')->value,
            $second->table('state')->value,
        );
    }

    public function testUnsafeIdentifierIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SqlIdentifier('users;drop_table');
    }

    public function testUnsafeStatementParameterNameIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new SqlStatement('SELECT 1', ['id OR 1=1' => 1]);
    }
}
