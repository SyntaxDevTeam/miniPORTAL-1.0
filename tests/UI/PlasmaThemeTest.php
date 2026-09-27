<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\UI\Catalog\BaseUiCatalog;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Plasma\PlasmaTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\Manifest\ThemeManifestParser;

final class PlasmaThemeTest extends TestCase
{
    public function testRendersPublicLayoutUsingBaseComponentFallback(): void
    {
        $page = new PageDefinition(
            'home',
            'Start <unsafe>',
            'public',
            [PageRegion::CONTENT => [new Card([new Text('Safe & sound')], 'Status')]],
            [new Breadcrumb('Start')],
            [new PageAction('admin', 'Panel', ActionIntent::Navigate, '/admin')],
        );

        $html = (new PlasmaTheme('/theme-assets'))->render($page);

        self::assertStringContainsString('<html lang="pl">', $html);
        self::assertStringContainsString('class="mp-plasma app-shell public-shell menu-expanded"', $html);
        self::assertStringContainsString('href="/theme-assets/theme.css"', $html);
        self::assertStringContainsString('src="/theme-assets/theme.js"', $html);
        self::assertStringContainsString('class="hero"', $html);
        self::assertStringContainsString('class="terminal"', $html);
        self::assertStringContainsString('role="dialog"', $html);
        self::assertStringContainsString('<section class="mp-card plasma-card">', $html);
        self::assertStringContainsString('Start &lt;unsafe&gt;', $html);
        self::assertStringContainsString('Safe &amp; sound', $html);
        self::assertStringNotContainsString('<unsafe>', $html);
    }

    public function testRendersApplicationLayoutAndSemanticAside(): void
    {
        $page = new PageDefinition('admin', 'Panel', 'application', [
            PageRegion::CONTENT => [new Text('Main')],
            PageRegion::ASIDE => [new Text('Aside')],
        ]);

        $html = (new PlasmaTheme())->render($page, 'pl-PL');

        self::assertStringContainsString('class="mp-plasma app-shell admin-shell menu-expanded"', $html);
        self::assertStringContainsString('class="admin-main"', $html);
        self::assertStringContainsString('<aside class="aside-region">', $html);
        self::assertStringContainsString('<html lang="pl-PL">', $html);
    }

    public function testRejectsUnsupportedLayout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PlasmaTheme())->render(new PageDefinition('auth', 'Login', 'auth', [
            PageRegion::CONTENT => [new Text('Login')],
        ]));
    }

    public function testRuntimeContractMatchesManifest(): void
    {
        $theme = new PlasmaTheme();
        $manifest = (new ThemeManifestParser())->parseFile(dirname(__DIR__, 2) . '/themes/plasma/theme.json');

        self::assertSame($manifest->id, $theme->id());
        self::assertSame($manifest->uiApiConstraint, $theme->uiApiConstraint());
        foreach ($manifest->layouts as $layout) {
            self::assertTrue($theme->supportsLayout($layout));
        }
        self::assertSame('/assets/themes/plasma/theme.js', $manifest->assets['script']);
        self::assertFileExists(dirname(__DIR__, 2) . '/public' . $manifest->assets['stylesheet']);
        self::assertFileExists(dirname(__DIR__, 2) . '/public' . $manifest->assets['script']);
        self::assertFileExists(dirname(__DIR__, 2) . '/public' . $manifest->assets['background']);
    }

    public function testOverridesOnlyCardAndInheritsRemainingBaseRenderers(): void
    {
        $registry = (new PlasmaTheme())->renderers();

        self::assertSame([Card::class], $registry->overriddenComponents());
        self::assertContains(Text::class, $registry->registeredComponents());
        self::assertStringContainsString('plasma-card', $registry->render(new Card([new Text('Inherited child')])));
        self::assertStringContainsString('mp-text', $registry->render(new Text('Base fallback')));
        self::assertStringContainsString('plasma-card', $registry->render(new Stack([new Card([new Text('Nested')])])));
    }

    public function testRendersCompleteUiCatalogInsideDashboardShell(): void
    {
        $page = (new BaseUiCatalog())->page();
        $html = (new PlasmaTheme())->render(new PageDefinition(
            $page->id,
            $page->title,
            'dashboard',
            $page->regions,
            $page->breadcrumbs,
            $page->actions,
        ));

        self::assertStringContainsString('class="admin-main"', $html);
        self::assertStringContainsString('class="mp-form"', $html);
        self::assertStringContainsString('class="mp-data-table"', $html);
        self::assertStringContainsString('class="mp-loading-state"', $html);
        self::assertStringContainsString('class="mp-permission-denied-state"', $html);
        self::assertStringContainsString('class="mp-degraded-state"', $html);
    }
}
