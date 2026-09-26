<?php

declare(strict_types=1);

use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContextFactory;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\NativeSessionStore;
use SyntaxDevTeam\MiniPortal\Library\Clock\Provider\SystemClock;
use SyntaxDevTeam\MiniPortal\UI\Catalog\BaseUiCatalog;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Component\TextField;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Model\TextTone;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\Model\InputType;
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
$authentication = $runtime->config->authentication === null
    ? null
    : new AuthenticationManager(
        $runtime->config->authentication,
        new NativeSessionStore($runtime->environment->isProduction()),
        new SystemClock(),
    );

if (!$router instanceof Router) {
    throw new LogicException('Router service has invalid type.');
}

if (!$contextFactory instanceof RequestContextFactory) {
    throw new LogicException('Request context factory service has invalid type.');
}

$router->add(
    'GET',
    '/login',
    'core.login',
    static function (Request $_) use ($themeResolver, $authentication): Response {
        if ($authentication === null) {
            return Response::text('Authentication is not configured.', 503)->withPrivateNoStore();
        }
        if ($authentication->current() !== null) {
            return Response::redirect('/admin');
        }
        $page = new PageDefinition('login', 'Logowanie', 'public', [PageRegion::CONTENT => [
            new Card([new Form('/login', FormMethod::Post, [
                new TextField('username', 'Nazwa użytkownika', required: true),
                new TextField('password', 'Hasło', InputType::Password, required: true),
            ], 'Zaloguj się', $authentication->csrfToken())], 'Panel administracyjny'),
        ]], [new Breadcrumb('Start', '/'), new Breadcrumb('Logowanie')]);
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page))->withPrivateNoStore();
    },
);

$router->add(
    'POST',
    '/login',
    'core.login.submit',
    static function (Request $request) use ($themeResolver, $authentication): Response {
        if ($authentication === null) {
            return Response::text('Authentication is not configured.', 503)->withPrivateNoStore();
        }
        if (!$authentication->verifyCsrf($request->formValue('_token'))) {
            return Response::text('Invalid CSRF token.', 403)->withPrivateNoStore();
        }
        $username = $request->formValue('username') ?? '';
        if ($authentication->login($username, $request->formValue('password') ?? '')) {
            return Response::redirect('/admin');
        }
        $page = new PageDefinition('login-failed', 'Logowanie', 'public', [PageRegion::CONTENT => [
            new Alert('Nieprawidłowa nazwa użytkownika lub hasło.', AlertSeverity::Error, 'Logowanie nie powiodło się'),
            new Card([new Form('/login', FormMethod::Post, [
                new TextField('username', 'Nazwa użytkownika', value: $username, required: true),
                new TextField('password', 'Hasło', InputType::Password, required: true),
            ], 'Spróbuj ponownie', $authentication->csrfToken())], 'Panel administracyjny'),
        ]]);
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page), 401)->withPrivateNoStore();
    },
);

$router->add(
    'POST',
    '/logout',
    'core.logout',
    static function (Request $request) use ($authentication): Response {
        if ($authentication === null || !$authentication->verifyCsrf($request->formValue('_token'))) {
            return Response::text('Invalid CSRF token.', 403)->withPrivateNoStore();
        }
        $authentication->logout();
        return Response::redirect('/login');
    },
);

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
    static function (Request $_) use ($themeResolver, $authentication): Response {
        if ($authentication === null || $authentication->current() === null) {
            return Response::redirect('/login');
        }
        $regions = (new BaseUiCatalog())->page()->regions;
        $regions[PageRegion::ASIDE][] = new Card([
            new Form('/logout', FormMethod::Post, [], 'Wyloguj się', $authentication->csrfToken()),
        ], 'Sesja administratora');
        $page = new PageDefinition(
            'admin-dashboard',
            'Panel administracyjny',
            'dashboard',
            $regions,
            [new Breadcrumb('Start', '/'), new Breadcrumb('Panel')],
            [new PageAction('refresh', 'Odśwież', ActionIntent::Refresh)],
        );
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page))->withPrivateNoStore();
    },
);

$session = $authentication?->current();
$request = Request::fromGlobals()->withContext($contextFactory->create(
    principalId: $session?->principalId,
    locale: 'pl',
    timezone: 'Europe/Warsaw',
    permissions: $session === null ? [] : ['*'],
    csrfToken: $session?->csrfToken,
));
$router->handle($request)->send();
