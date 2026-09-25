<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Catalog;

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
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Model\TextTone;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\Model\InputType;
use SyntaxDevTeam\MiniPortal\UI\Model\TableColumn;
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
                new Card([new Form('/catalog/example', FormMethod::Post, [
                    new TextField('email', 'E-mail', InputType::Email, required: true, help: 'Adres używany do powiadomień.'),
                    new SelectField('database', 'Silnik bazy', ['mysql' => 'MySQL', 'pgsql' => 'PostgreSQL'], 'pgsql', true),
                    new CheckboxField('maintenance', 'Tryb konserwacji'),
                ], 'Zapisz ustawienia', 'catalog-csrf-token')], 'Form API'),
                new LoadingState('Ładowanie danych…'),
                new EmptyState('Brak wyników', 'Zmień filtry lub wyszukiwaną frazę.'),
                new ErrorState('Nie udało się pobrać danych', 'Spróbuj ponownie później.', 'catalog-error-01'),
                new DataTable([
                    new TableColumn('name', 'Nazwa'),
                    new TableColumn('status', 'Status'),
                    new TableColumn('jobs', 'Zadania', true),
                ], [
                    ['name' => 'Core', 'status' => 'online', 'jobs' => 4],
                    ['name' => 'Worker', 'status' => 'idle', 'jobs' => 0],
                ], 'Usługi platformy'),
                new Pagination(2, 3, '/catalog?page=1', '/catalog?page=3'),
            ], new ComponentIdentity('catalog.content'))]],
        );
    }
}
