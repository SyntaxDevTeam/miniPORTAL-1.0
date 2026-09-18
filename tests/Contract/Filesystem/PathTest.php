<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Filesystem;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Exception\ScopeViolation;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Model\Path;

final class PathTest extends TestCase
{
    public function testNormalizesScopeRootedLogicalPaths(): void
    {
        $path = Path::fromString('/plugins/./Example/config.yml');

        self::assertSame('plugins/Example/config.yml', $path->value);
        self::assertSame('/plugins/Example/config.yml', $path->display());
    }

    public function testRootHasExplicitRepresentation(): void
    {
        self::assertTrue(Path::fromString('/')->isRoot());
        self::assertSame('/', Path::root()->display());
    }

    public function testTraversalSegmentIsRejected(): void
    {
        $this->expectException(ScopeViolation::class);

        Path::fromString('plugins/../server.properties');
    }
}
