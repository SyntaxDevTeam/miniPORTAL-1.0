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
use SyntaxDevTeam\MiniPortal\UI\Component\CheckboxField;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Component\SelectField;
use SyntaxDevTeam\MiniPortal\UI\Component\TextField;
use SyntaxDevTeam\MiniPortal\UI\Component\DataTable;
use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Component\ErrorState;
use SyntaxDevTeam\MiniPortal\UI\Component\LoadingState;
use SyntaxDevTeam\MiniPortal\UI\Component\Pagination;
use SyntaxDevTeam\MiniPortal\UI\Component\PermissionDeniedState;
use SyntaxDevTeam\MiniPortal\UI\Component\DegradedState;
use SyntaxDevTeam\MiniPortal\UI\Component\TableQueryControls;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererNotFound;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\Model\InputType;

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
            Form::class,
            TextField::class,
            SelectField::class,
            CheckboxField::class,
            EmptyState::class,
            ErrorState::class,
            LoadingState::class,
            DataTable::class,
            Pagination::class,
            PermissionDeniedState::class,
            DegradedState::class,
            TableQueryControls::class,
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
        self::assertStringContainsString('mp-form', $html);
        self::assertStringContainsString('PostgreSQL', $html);
        self::assertStringContainsString('mp-loading-state', $html);
        self::assertStringContainsString('mp-empty-state', $html);
        self::assertStringContainsString('mp-error-state', $html);
        self::assertStringContainsString('mp-data-table', $html);
        self::assertStringContainsString('mp-pagination', $html);
        self::assertStringContainsString('mp-permission-denied-state', $html);
        self::assertStringContainsString('mp-degraded-state', $html);
        self::assertStringContainsString('mp-table-query', $html);
        self::assertStringContainsString('aria-sort="ascending"', $html);
        self::assertStringContainsString('data-row-id="service:core"', $html);
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

    public function testFormRendererEscapesValuesAndProvidesAccessibleValidationState(): void
    {
        $form = new Form('/settings', FormMethod::Post, [
            new TextField('email', 'E-mail', InputType::Email, 'a&b@example.test', true, 'Pomoc', 'Niepoprawny <adres>'),
            new SelectField('database', 'Baza', ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL'], 'pgsql'),
            new CheckboxField('enabled', 'Aktywna', true),
        ], 'Zapisz <teraz>', 'token&safe');

        $html = (new BaseTheme())->renderers()->render($form);

        self::assertStringContainsString('method="post"', $html);
        self::assertStringContainsString('name="_token" value="token&amp;safe"', $html);
        self::assertStringContainsString('aria-invalid="true"', $html);
        self::assertStringContainsString('value="pgsql" selected', $html);
        self::assertStringContainsString('type="checkbox" value="1" checked', $html);
        self::assertStringContainsString('Zapisz &lt;teraz&gt;', $html);
        self::assertStringNotContainsString('<adres>', $html);
    }

    public function testPostFormRequiresCsrfToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Form('/save', FormMethod::Post, [new TextField('name', 'Nazwa')], 'Zapisz');
    }
}
