<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ThemeUiRenderer;
use SyntaxDevTeam\MiniPortal\UI\Theme\Plasma\PlasmaTheme;

final class ThemeUiRendererTest extends TestCase
{
    public function testFullPageRenderingDelegatesToActiveTheme(): void
    {
        $renderer = new ThemeUiRenderer(new PlasmaTheme('/assets'));
        $page = new PageDefinition('dashboard', 'Panel', 'dashboard', [
            PageRegion::CONTENT => [new Text('Treść')],
        ]);

        $html = $renderer->render($page, 'pl-PL');

        self::assertStringContainsString('class="mp-plasma admin-shell"', $html);
        self::assertStringContainsString('href="/assets/theme.css"', $html);
        self::assertStringContainsString('<html lang="pl-PL">', $html);
    }

    public function testSingleComponentUsesActiveThemeInheritanceChain(): void
    {
        $renderer = new ThemeUiRenderer(new PlasmaTheme());

        $html = $renderer->renderComponent(new Stack([
            new Card([new Text('Nested & safe')], 'Karta'),
        ]));

        self::assertStringContainsString('class="mp-stack"', $html);
        self::assertStringContainsString('class="mp-card plasma-card"', $html);
        self::assertStringContainsString('Nested &amp; safe', $html);
    }

    public function testNamedRegionRendersOnlyRequestedRegion(): void
    {
        $renderer = new ThemeUiRenderer(new PlasmaTheme());
        $page = new PageDefinition('fragments', 'Fragmenty', 'application', [
            PageRegion::CONTENT => [new Text('Treść główna')],
            PageRegion::ASIDE => [new Card([new Text('Boczna')], 'Aside')],
        ]);

        $html = $renderer->renderRegion($page, PageRegion::ASIDE);

        self::assertStringContainsString('plasma-card', $html);
        self::assertStringContainsString('Boczna', $html);
        self::assertStringNotContainsString('Treść główna', $html);
        self::assertStringNotContainsString('<!doctype html>', $html);
    }

    public function testMissingValidRegionRendersEmptyFragment(): void
    {
        $renderer = new ThemeUiRenderer(new PlasmaTheme());
        $page = new PageDefinition('fragments', 'Fragmenty', 'application', [
            PageRegion::CONTENT => [new Text('Treść')],
        ]);

        self::assertSame('', $renderer->renderRegion($page, PageRegion::ASIDE));
    }

    public function testInvalidRegionIdentifierIsRejectedByPageContract(): void
    {
        $renderer = new ThemeUiRenderer(new PlasmaTheme());
        $page = new PageDefinition('fragments', 'Fragmenty', 'application', [
            PageRegion::CONTENT => [new Text('Treść')],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $renderer->renderRegion($page, 'invalid region');
    }
}
