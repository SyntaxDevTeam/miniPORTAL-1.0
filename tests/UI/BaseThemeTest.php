<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\UI\FixtureComponent;
use SyntaxDevTeam\MiniPortal\UI\Catalog\BaseUiCatalog;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererNotFound;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;

final class BaseThemeTest extends TestCase
{
    public function testEveryCurrentPublicComponentHasBaseRenderer(): void
    {
        $registered = (new BaseTheme())->renderers()->registeredComponents();

        self::assertEqualsCanonicalizing([
            Text::class,
            Heading::class,
            Alert::class,
            Stack::class,
            Card::class,
        ], $registered);
    }

    public function testNestedComponentsRenderSemanticEscapedHtml(): void
    {
        $component = new Card([
            new Stack([
                new Heading('<Unsafe>', 3),
                new Text('Text & more'),
                new Alert('Failed <script>', AlertSeverity::Error, 'Problem'),
            ]),
        ], 'Overview', new ComponentIdentity('dashboard.card'));

        $html = (new BaseTheme())->renderers()->render($component);

        self::assertStringContainsString('<section class="mp-card" data-component-id="dashboard.card">', $html);
        self::assertStringContainsString('<h3 class="mp-heading">&lt;Unsafe&gt;</h3>', $html);
        self::assertStringContainsString('Text &amp; more', $html);
        self::assertStringContainsString('role="alert"', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testCatalogExercisesEveryRegisteredComponent(): void
    {
        $registry = (new BaseTheme())->renderers();
        $page = (new BaseUiCatalog())->page();
        $html = $registry->renderMany($page->region(PageRegion::CONTENT));

        self::assertStringContainsString('Typography', $html);
        self::assertStringContainsString('mp-text--muted', $html);
        self::assertStringContainsString('mp-alert--error', $html);
        self::assertStringContainsString('mp-card', $html);
    }

    public function testUnknownComponentFailsExplicitly(): void
    {
        $this->expectException(RendererNotFound::class);
        (new BaseTheme())->renderers()->render(new FixtureComponent());
    }
}
