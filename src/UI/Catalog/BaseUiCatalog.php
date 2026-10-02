<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Catalog;

use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\WidgetSlot;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Component\CheckboxField;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Component\SelectField;
use SyntaxDevTeam\MiniPortal\UI\Component\TextField;
use SyntaxDevTeam\MiniPortal\UI\Component\TextAreaField;
use SyntaxDevTeam\MiniPortal\UI\Component\RichText;
use SyntaxDevTeam\MiniPortal\UI\Model\ContentFormat;
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
use SyntaxDevTeam\MiniPortal\UI\Model\TextTone;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\Model\InputType;
use SyntaxDevTeam\MiniPortal\UI\Model\TableColumn;
use SyntaxDevTeam\MiniPortal\UI\Model\TableFilter;
use SyntaxDevTeam\MiniPortal\UI\Model\TableRow;
use SyntaxDevTeam\MiniPortal\UI\Model\SortDirection;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;

final class BaseUiCatalog
{
    public function page(): PageDefinition
    {
        return new PageDefinition(
            'ui-catalog-base',
            'Base UI Catalog',
            'application',
            [PageRegion::CONTENT => [new Stack([
                new Heading('Typography', 2),
                new Text('Default text'),
                new Text('Muted text', TextTone::Muted),
                new Alert('Informational message', AlertSeverity::Info),
                new Alert('Operation failed', AlertSeverity::Error, 'Error'),
                new Card([new Text('Card content')], 'Card title'),
                new WidgetSlot('catalog.inline', [new Text('Widget placement example')]),
                new RichText("## Treść Markdown\n\nBezpieczny [link](https://example.org)."),
                new RichText('<p>Starsza <strong>treść HTML</strong>.</p>', ContentFormat::Html),
                new Card([new Form('/catalog/example', FormMethod::Post, [
                    new TextField('email', 'E-mail', InputType::Email, required: true, help: 'Adres używany do powiadomień.'),
                    new TextAreaField('description', 'Opis', "Pierwsza linia\nDruga linia", help: 'Wiele wierszy treści.'),
                    new SelectField('database', 'Silnik bazy', ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL'], 'pgsql', true),
                    new CheckboxField('maintenance', 'Tryb konserwacji'),
                ], 'Zapisz ustawienia', 'catalog-csrf-token')], 'Form API'),
                new LoadingState('Ładowanie danych…'),
                new EmptyState('Brak wyników', 'Zmień filtry lub wyszukiwaną frazę.'),
                new ErrorState('Nie udało się pobrać danych', 'Spróbuj ponownie później.', 'catalog-error-01'),
                new DataTable([
                    new TableColumn('name', 'Nazwa', sortUrl: '/catalog?sort=name&direction=desc', sortDirection: SortDirection::Ascending),
                    new TableColumn('status', 'Status', sortUrl: '/catalog?sort=status&direction=asc'),
                    new TableColumn('jobs', 'Zadania', true),
                ], [
                    new TableRow('service:core', ['name' => 'Core', 'status' => 'online', 'jobs' => 4]),
                    new TableRow('service:worker', ['name' => 'Worker', 'status' => 'idle', 'jobs' => 0]),
                ], 'Usługi platformy', componentIdentity: new ComponentIdentity('catalog.services'), queryControls: new TableQueryControls(
                    '/catalog',
                    'core',
                    filters: [new TableFilter('status', 'Status', ['' => 'Wszystkie', 'online' => 'Online', 'idle' => 'Idle'], '')],
                    preservedParameters: ['sort' => 'name'],
                ), pagination: new Pagination(2, 3, '/catalog?page=1', '/catalog?page=3')),
                new PermissionDeniedState('Brak dostępu', 'Nie masz uprawnienia do tej sekcji.', 'admin.system.read'),
                new DegradedState('Dane częściowe', 'Jedno ze źródeł jest chwilowo niedostępne.', [
                    new Text('Pozostałe dane pozostają dostępne.', TextTone::Muted),
                ]),
            ], new ComponentIdentity('catalog.content'))]],
        );
    }
}
