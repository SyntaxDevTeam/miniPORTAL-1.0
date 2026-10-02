<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Application;

use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

/** Read-only package inventory. Lifecycle writes remain behind preflight and Core policy. */
final readonly class AdminPackages
{
    public function __construct(
        private AuthenticationManager $authentication,
        private PackageRegistry $registry,
        private RequiredPackagePolicy $required,
        private ThemeResolver $themes,
    ) {
    }

    public function register(Router $router): void
    {
        $router->add('GET', '/admin/modules', 'core.admin.modules', $this->index(...));
    }

    private function index(Request $_): Response
    {
        $session = $this->authentication->current();
        if ($session === null) {
            return Response::redirect('/login');
        }
        if (!in_array('*', $session->permissions, true) && !in_array('admin.access', $session->permissions, true)) {
            return Response::text('Access denied.', 403)->withPrivateNoStore();
        }
        $components = [];
        foreach ($this->registry->allReleases() as $release) {
            $id = $release->manifest->id;
            $active = $this->registry->active($id)?->manifest->version === $release->manifest->version;
            $components[] = new Card([
                new Text('Typ: ' . $release->manifest->type->value),
                new Text('Stan: ' . $release->state->value),
                new Text('Aktywna wersja: ' . ($active ? 'tak' : 'nie')),
                new Text('Wymagany moduł systemowy: ' . ($this->required->isRequired($id) ? 'tak' : 'nie')),
            ], $release->manifest->name . ' · ' . $id . ' · ' . $release->manifest->version);
        }
        if ($components === []) {
            $components[] = new EmptyState('Brak pakietów', 'Żaden pakiet nie został jeszcze zarejestrowany.');
        }
        $page = new PageDefinition(
            'admin-modules', 'Moduły i pakiety', 'dashboard', [PageRegion::CONTENT => $components],
            [new Breadcrumb('Panel', '/admin'), new Breadcrumb('Moduły i pakiety')],
        );
        return Response::html($this->themes->resolve('plasma', $page->layoutRole)->theme->render($page))
            ->withPrivateNoStore();
    }
}
