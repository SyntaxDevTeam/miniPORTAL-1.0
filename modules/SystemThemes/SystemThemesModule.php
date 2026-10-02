<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\SystemThemes;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

/** Required system module: presents the theme registry through the public UI API. */
final readonly class SystemThemesModule implements Module
{
    public function __construct(private ThemeResolver $themes, private string $selectedTheme = 'plasma')
    {
    }

    public function register(ModuleRegistration $registration): void
    {
        $registration->routes->get('/', 'index', $this->index(...));
        $registration->api?->get('/themes', 'themes', 'themes.read', $this->apiThemes(...));
    }

    public function boot(ModuleContext $context): void
    {
    }

    private function apiThemes(Request $_): Response
    {
        return Response::json(['themes' => $this->themes->registeredThemeIds(), 'selected' => $this->selectedTheme]);
    }

    private function index(Request $request): Response
    {
        if ($request->context === null || !$request->context->hasPermission('settings.manage')) {
            return Response::text('Access denied.', 403)->withPrivateNoStore();
        }
        $cards = [];
        foreach ($this->themes->registeredThemeIds() as $id) {
            $cards[] = new Card([
                new Text($id === $this->selectedTheme ? 'Aktywny szablon' : 'Dostępny szablon'),
                new Text($id === 'base' ? 'Gwarantowany fallback UI.' : 'Renderowanie przez publiczny kontrakt UI.'),
            ], $id);
        }
        $page = new PageDefinition(
            'system-themes',
            'Szablony',
            'dashboard',
            [PageRegion::CONTENT => $cards],
            [new Breadcrumb('Panel', '/admin'), new Breadcrumb('Szablony')],
        );
        return Response::html($this->themes->resolve($this->selectedTheme, $page->layoutRole)->theme->render($page))
            ->withPrivateNoStore();
    }
}
