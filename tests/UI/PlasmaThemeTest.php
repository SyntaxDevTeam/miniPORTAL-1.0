<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Plasma\PlasmaTheme;

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
        self::assertStringContainsString('class="mp-plasma public-shell"', $html);
        self::assertStringContainsString('href="/theme-assets/theme.css"', $html);
        self::assertStringContainsString('<section class="mp-card">', $html);
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

        self::assertStringContainsString('class="mp-plasma admin-shell"', $html);
        self::assertStringContainsString('<section class="aside-region">', $html);
        self::assertStringContainsString('<html lang="pl-PL">', $html);
    }

    public function testRejectsUnsupportedLayout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PlasmaTheme())->render(new PageDefinition('auth', 'Login', 'auth', [
            PageRegion::CONTENT => [new Text('Login')],
        ]));
    }
}
