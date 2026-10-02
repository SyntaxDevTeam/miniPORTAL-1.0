<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\SystemThemes;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleFactory;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleServices;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\UiFacade;

/** Required system module: presents the theme registry through the public UI API. */
final readonly class SystemThemesModule implements Module, ModuleFactory
{
    public function __construct(private UiFacade $ui, private string $selectedTheme = 'plasma')
    {
    }

    public static function create(ModuleServices $services): Module
    {
        return new self($services->ui);
    }

    public function register(ModuleRegistration $registration): void
    {
        $registration->navigation?->add('themes', 'Szablony', '/', 'admin');
        $registration->routes->get('/', 'index', $this->index(...));
        $registration->api?->get('/themes', 'themes', 'themes.read', $this->apiThemes(...));
    }

    public function boot(ModuleContext $context): void
    {
    }

    private function apiThemes(Request $_): Response
    {
        return Response::json(['themes' => $this->ui->registeredThemeIds(), 'selected' => $this->selectedTheme]);
    }

    private function index(Request $request): Response
    {
        if ($request->context === null || !$request->context->hasPermission('settings.manage')) {
            return Response::text('Access denied.', 403)->withPrivateNoStore();
        }
        $cards = [];
        foreach ($this->ui->registeredThemeIds() as $id) {
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
        return Response::html($this->ui->render($page, $this->selectedTheme))
            ->withPrivateNoStore();
    }
}
