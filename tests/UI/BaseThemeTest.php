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
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;

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

    public function testProvidesSafeEmergencyPageForEveryLayoutRole(): void
    {
        $page = new PageDefinition(
            'emergency',
            'Fallback <safe>',
            'future-layout',
            [PageRegion::CONTENT => [new Text('Content & status')]],
            actions: [new PageAction('return', 'Wróć', ActionIntent::Navigate, '/')],
        );

        $html = (new BaseTheme())->render($page);

        self::assertStringContainsString('<title>Fallback &lt;safe&gt;</title>', $html);
        self::assertStringContainsString('Content &amp; status', $html);
        self::assertStringContainsString('href="/"', $html);
        self::assertStringNotContainsString('<safe>', $html);
    }
}
