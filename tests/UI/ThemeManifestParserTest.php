<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\UI\Theme\Manifest\ThemeManifestException;
use SyntaxDevTeam\MiniPortal\UI\Theme\Manifest\ThemeManifestParser;

final class ThemeManifestParserTest extends TestCase
{
    public function testParsesProductionPlasmaManifest(): void
    {
        $manifest = (new ThemeManifestParser())->parseFile(dirname(__DIR__, 2) . '/themes/plasma/theme.json');

        self::assertSame('plasma', $manifest->id);
        self::assertSame('^1.0', $manifest->uiApiConstraint);
        self::assertSame('base', $manifest->extends);
        self::assertContains('dashboard', $manifest->layouts);
        self::assertSame('/assets/themes/plasma/theme.css', $manifest->assets['stylesheet']);
    }

    public function testCollectsStructuralAndSecurityErrors(): void
    {
        try {
            (new ThemeManifestParser())->parse('{"schema":2,"id":"Bad ID","name":"Bad","version":"next","requires":{"uiApi":"^1.0"},"extends":"base","layouts":["Bad Role"],"assets":{"stylesheet":"../secret"},"preferredColorScheme":"neon"}');
            self::fail('Invalid manifest should fail.');
        } catch (ThemeManifestException $exception) {
            self::assertGreaterThanOrEqual(5, count($exception->errors));
            self::assertStringContainsString('safe absolute URL path', $exception->getMessage());
        }
    }

    public function testRejectsUnreadableManifest(): void
    {
        $this->expectException(ThemeManifestException::class);
        (new ThemeManifestParser())->parseFile('/path/that/does/not/exist/theme.json');
    }
}
