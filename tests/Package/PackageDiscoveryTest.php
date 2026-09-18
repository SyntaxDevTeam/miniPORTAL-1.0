<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;

final class PackageDiscoveryTest extends TestCase
{
    public function testDiscoveryReadsManifestWithoutExecutingEntrypointCode(): void
    {
        $root = dirname(__DIR__) . '/Fixtures/packages';
        $marker = $root . '/fixture-good/entrypoint-executed.marker';
        @unlink($marker);

        $report = (new PackageDiscovery(new ManifestParser()))->discover($root);

        self::assertCount(1, $report->packages);
        self::assertCount(1, $report->failures);
        self::assertSame('fixture-good', $report->packages[0]->manifest->id);
        self::assertFileDoesNotExist($marker);
    }
}
