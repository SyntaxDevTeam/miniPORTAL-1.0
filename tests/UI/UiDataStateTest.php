<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\UI\Component\DataTable;
use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Component\ErrorState;
use SyntaxDevTeam\MiniPortal\UI\Component\LoadingState;
use SyntaxDevTeam\MiniPortal\UI\Component\Pagination;
use SyntaxDevTeam\MiniPortal\UI\Component\TableQueryControls;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\TableColumn;
use SyntaxDevTeam\MiniPortal\UI\Model\TableRow;
use SyntaxDevTeam\MiniPortal\UI\Model\TableFilter;
use SyntaxDevTeam\MiniPortal\UI\Model\SortDirection;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;

final class UiDataStateTest extends TestCase
{
    public function testDataTableEscapesHeadersCaptionAndCells(): void
    {
        $table = new DataTable(
            [
                new TableColumn('name', '<Nazwa>'),
                new TableColumn('count', 'Liczba', true),
            ],
            [
                ['name' => '<script>alert(1)</script>', 'count' => 7],
                ['name' => 'Worker & API', 'count' => null],
            ],
            'Stan <usług>',
            componentIdentity: new ComponentIdentity('services.table'),
        );

        $html = (new BaseTheme())->renderers()->render($table);

        self::assertStringContainsString('data-component-id="services.table"', $html);
        self::assertStringContainsString('&lt;Nazwa&gt;', $html);
        self::assertStringContainsString('Stan &lt;usług&gt;', $html);
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        self::assertStringContainsString('Worker &amp; API', $html);
        self::assertStringContainsString('aria-label="Brak danych"', $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testStableTableRowIdentityIsRenderedWithoutBreakingLegacyRows(): void
    {
        $table = new DataTable(
            [new TableColumn('name', 'Nazwa')],
            [
                new TableRow('server:alpha', ['name' => 'Alpha']),
                ['name' => 'Legacy'],
            ],
        );

        $html = (new BaseTheme())->renderers()->render($table);

        self::assertStringContainsString('data-row-id="server:alpha"', $html);
        self::assertSame(
            [['name' => 'Alpha'], ['name' => 'Legacy']],
            $table->rows(),
        );
        self::assertNull($table->tableRows()[1]->id);
    }

    public function testTableRowRejectsUnsafeIdentity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TableRow('<unsafe>', ['name' => 'Alpha']);
    }

    public function testEmptyDataTableRequiresAndRendersExplicitEmptyState(): void
    {
        $table = new DataTable(
            [new TableColumn('name', 'Nazwa')],
            [],
            emptyState: new EmptyState('Brak danych', 'Nie znaleziono rekordów.'),
        );

        $html = (new BaseTheme())->renderers()->render($table);

        self::assertStringContainsString('mp-data-table--empty', $html);
        self::assertStringContainsString('Brak danych', $html);
        self::assertStringNotContainsString('<table>', $html);
    }

    public function testEmptyDataTableWithoutStateIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DataTable([new TableColumn('name', 'Nazwa')], []);
    }

    public function testRowsMustMatchDeclaredColumns(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new DataTable(
            [new TableColumn('name', 'Nazwa'), new TableColumn('status', 'Status')],
            [['name' => 'Core']],
        );
    }

    public function testTableRendersServerDrivenQueryControlsSortAndPagination(): void
    {
        $table = new DataTable(
            [
                new TableColumn('name', 'Nazwa', sortUrl: '/items?sort=name&dir=desc', sortDirection: SortDirection::Ascending),
                new TableColumn('status', 'Status'),
            ],
            [new TableRow('item:1', ['name' => 'A & B', 'status' => 'online'])],
            queryControls: new TableQueryControls(
                '/items',
                '<query>',
                filters: [new TableFilter('status', 'Status', ['' => 'Wszystkie', 'online' => 'Online'], 'online')],
                preservedParameters: ['sort' => 'name'],
            ),
            pagination: new Pagination(1, 2, nextUrl: '/items?page=2'),
        );

        $html = (new BaseTheme())->renderers()->render($table);

        self::assertStringContainsString('role="search"', $html);
        self::assertStringContainsString('value="&lt;query&gt;"', $html);
        self::assertStringContainsString('name="status"', $html);
        self::assertStringContainsString('value="online" selected', $html);
        self::assertStringContainsString('name="sort" value="name"', $html);
        self::assertStringContainsString('aria-sort="ascending"', $html);
        self::assertStringContainsString('href="/items?sort=name&amp;dir=desc"', $html);
        self::assertStringContainsString('mp-pagination', $html);
    }

    public function testQueryControlsRejectDuplicateParameterNames(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TableQueryControls(
            '/items',
            filters: [new TableFilter('q', 'Status', ['' => 'Wszystkie'])],
        );
    }

    public function testSortableColumnRejectsUnsafeUrl(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TableColumn('name', 'Nazwa', sortUrl: 'javascript:alert(1)');
    }

    public function testPaginationRendersServerDrivenBoundaryLinks(): void
    {
        $pagination = new Pagination(2, 4, '/items?page=1&filter=a', '/items?page=3&filter=a');

        $html = (new BaseTheme())->renderers()->render($pagination);

        self::assertStringContainsString('rel="prev"', $html);
        self::assertStringContainsString('href="/items?page=1&amp;filter=a"', $html);
        self::assertStringContainsString('aria-current="page">Strona 2 z 4', $html);
        self::assertStringContainsString('rel="next"', $html);
    }

    public function testPaginationRejectsUnsafeOrInconsistentLinks(): void
    {
        try {
            new Pagination(2, 3, 'javascript:alert(1)', '/items?page=3');
            self::fail('Unsafe pagination URL should be rejected.');
        } catch (\InvalidArgumentException) {
        }

        $this->expectException(\InvalidArgumentException::class);
        new Pagination(1, 3, '/items?page=0', '/items?page=2');
    }

    public function testFeedbackStatesExposeAccessibleRolesWithoutRawHtml(): void
    {
        $registry = (new BaseTheme())->renderers();

        $loading = $registry->render(new LoadingState('Ładowanie <danych>'));
        $empty = $registry->render(new EmptyState('Brak <rekordów>', 'Zmień filtr.'));
        $error = $registry->render(new ErrorState('Błąd <odczytu>', 'Spróbuj ponownie.', 'err<&>'));

        self::assertStringContainsString('role="status"', $loading);
        self::assertStringContainsString('aria-busy="true"', $loading);
        self::assertStringContainsString('Ładowanie &lt;danych&gt;', $loading);
        self::assertStringContainsString('Brak &lt;rekordów&gt;', $empty);
        self::assertStringContainsString('role="alert"', $error);
        self::assertStringContainsString('err&lt;&amp;&gt;', $error);
    }
}
