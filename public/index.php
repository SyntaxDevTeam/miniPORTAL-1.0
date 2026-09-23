<?php

declare(strict_types=1);

use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContextFactory;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\UI\Catalog\BaseUiCatalog;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Model\TextTone;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\Plasma\PlasmaTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

require dirname(__DIR__) . '/vendor/autoload.php';

$runtime = Runtime::boot();
$services = (new CompositionRoot())->build($runtime);
$router = $services->get(Router::class);
$contextFactory = $services->get(RequestContextFactory::class);
$themeResolver = new ThemeResolver(new BaseTheme(), '1.0.0');
$themeResolver->register(new PlasmaTheme());

if (!$router instanceof Router) {
    throw new LogicException('Router service has invalid type.');
}

if (!$contextFactory instanceof RequestContextFactory) {
    throw new LogicException('Request context factory service has invalid type.');
}

$router->add(
    'GET',
    '/',
    'core.home',
    static function (Request $_) use ($themeResolver, $runtime): Response {
        $page = new PageDefinition(
            'home',
            'Nowoczesny fundament usług SyntaxDevTeam',
            'public',
            [
                PageRegion::PAGE_HEADER => [new Text('Modularny portal i panel administracyjny budowany wokół stabilnych kontraktów, bezpiecznych aktualizacji oraz wymiennych motywów.', TextTone::Muted)],
                PageRegion::CONTENT => [new Stack([
                    new Alert('Core, routing i pierwszy produkcyjny motyw działają poprawnie.', AlertSeverity::Success, 'System online'),
                    new Card([new Heading('Architektura przede wszystkim', 2), new Text('Moduły opisują semantykę strony, a Plasma odpowiada za jej wygląd. Dzięki temu panel może ewoluować bez wiązania domeny z HTML-em.')], 'miniPORTAL 1.0'),
                    new Card([new Heading('MySQL lub PostgreSQL', 2), new Text('Warstwa storage pozostaje niezależna od silnika bazy danych, a instalator będzie prowadził przez wybór właściwego adaptera.')], 'Elastyczne wdrożenie'),
                ])],
                PageRegion::ASIDE => [new Heading('Stan prac', 2), new Text('UI API i Base Theme'), new Text('Plasma Theme: pierwszy szkielet'), new Text('Request: ' . (string) $runtime->correlationId, TextTone::Muted)],
                PageRegion::FOOTER => [new Text('SyntaxDevTeam · miniPORTAL 1.0', TextTone::Muted)],
            ],
            [new Breadcrumb('Start')],
            [new PageAction('open-admin', 'Otwórz panel', ActionIntent::Navigate, '/admin')],
        );
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page));
    },
);

$router->add(
    'GET',
    '/admin',
    'core.admin',
    static function (Request $_) use ($themeResolver): Response {
        $page = new PageDefinition(
            'admin-dashboard',
            'Panel administracyjny',
            'dashboard',
            (new BaseUiCatalog())->page()->regions,
            [new Breadcrumb('Start', '/'), new Breadcrumb('Panel')],
            [new PageAction('refresh', 'Odśwież', ActionIntent::Refresh)],
        );
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page));
    },
);

$request = Request::fromGlobals()->withContext($contextFactory->create());
$router->handle($request)->send();
