<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;

final class VersionConstraintTest extends TestCase
{
    #[DataProvider('cases')]
    public function testMatchesSupportedConstraints(string $version, string $constraint, bool $expected): void
    {
        self::assertSame($expected, (new VersionConstraint())->matches($version, $constraint));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function cases(): iterable
    {
        yield 'wildcard' => ['1.2.3', '*', true];
        yield 'exact true' => ['1.2.3', '1.2.3', true];
        yield 'exact false' => ['1.2.4', '1.2.3', false];
        yield 'caret major' => ['1.9.0', '^1.2', true];
        yield 'caret next major' => ['2.0.0', '^1.2', false];
        yield 'caret zero minor' => ['0.2.9', '^0.2.3', true];
        yield 'caret zero next minor' => ['0.3.0', '^0.2.3', false];
        yield 'greater equal' => ['1.2.3', '>=1.2.0', true];
        yield 'less' => ['1.2.3', '<2.0.0', true];
    }
}
