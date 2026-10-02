<?php

declare(strict_types=1);

use SyntaxDevTeam\MiniPortal\Application\AdminAccounts;
use SyntaxDevTeam\MiniPortal\Application\AdminPackages;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Module\ActiveModuleMount;
use SyntaxDevTeam\MiniPortal\Core\Module\ActiveModuleLoader;
use SyntaxDevTeam\MiniPortal\Core\Capability\CapabilityRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Module\SystemThemes\SystemThemesModule;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContextFactory;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetCatalog;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetComposer;
use SyntaxDevTeam\MiniPortal\Core\Api\ServiceApiGateway;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\IdentityProviderFactory;
use SyntaxDevTeam\MiniPortal\Core\Security\IdentityProviderRegistry;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthFlow;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthAttemptLimiter;
use SyntaxDevTeam\MiniPortal\Core\Security\OAuthRateLimitExceeded;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\NativeOAuthStateStore;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\NativeSessionStore;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\NativeOAuthAttemptStore;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\AllowListIdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseAccountLifecycle;
use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\ScopedAuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Clock\Provider\SystemClock;
use SyntaxDevTeam\MiniPortal\Library\Http\Provider\StreamHttpClient;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\UI\Catalog\BaseUiCatalog;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\Heading;
use SyntaxDevTeam\MiniPortal\UI\Component\Stack;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Model\TextTone;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\Plasma\PlasmaTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

require dirname(__DIR__) . '/vendor/autoload.php';

$runtime = Runtime::boot();
$services = (new CompositionRoot())->build($runtime);
$router = $services->get(Router::class);
$contextFactory = $services->get(RequestContextFactory::class);
$logger = $services->get(Logger::class);
$themeResolver = new ThemeResolver(new BaseTheme(), '1.0.0');
$themeResolver->register(new PlasmaTheme());
$widgetCatalog = $services->get(WidgetCatalog::class);
$widgetComposer = $services->get(WidgetComposer::class);
$apiGateway = $services->has(ServiceApiGateway::class) ? $services->get(ServiceApiGateway::class) : null;
$authentication = null;
$accountDirectory = null;
$accountLifecycle = null;
$providers = new IdentityProviderRegistry();
$oauth = null;
if ($runtime->config->authentication !== null) {
    $clock = new SystemClock();
    $factory = new IdentityProviderFactory(new StreamHttpClient());
    $providers = new IdentityProviderRegistry(array_map(
        $factory->create(...),
        $runtime->config->authentication->providers,
    ));
    $accounts = new AllowListIdentityAccountRepository($runtime->config->authentication);
    if ($services->has(Database::class)) {
        $database = $services->get(Database::class);
        $auditSink = $services->get(AuditSink::class);
        if (!$database instanceof Database || !$auditSink instanceof AuditSink) {
            throw new LogicException('Database or audit service has invalid type.');
        }
        $accountDirectory = new DatabaseIdentityAccountRepository($database, $clock);
        $accounts = $accountDirectory;
        $accountLifecycle = new DatabaseAccountLifecycle(
            $database,
            new ScopedAuditTrail('core.security', $auditSink, $clock),
        );
    }
    $authentication = new AuthenticationManager(
        $runtime->config->authentication,
        new NativeSessionStore($runtime->environment->isProduction()),
        $clock,
        $accountDirectory,
    );
    $oauth = new OAuthFlow(
        $providers,
        new NativeOAuthStateStore(),
        $accounts,
        $authentication,
        new OAuthAttemptLimiter(new NativeOAuthAttemptStore(), $clock),
        $clock,
    );
}

if (!$router instanceof Router) {
    throw new LogicException('Router service has invalid type.');
}

if (!$contextFactory instanceof RequestContextFactory) {
    throw new LogicException('Request context factory service has invalid type.');
}

if (!$logger instanceof Logger) {
    throw new LogicException('Logger service has invalid type.');
}

if ($authentication !== null && $accountDirectory !== null && $accountLifecycle !== null) {
    (new AdminAccounts($authentication, $accountDirectory, $accountLifecycle, $themeResolver, $logger))
        ->register($router);
    $packageRegistry = $services->get(PackageRegistry::class);
    if ($packageRegistry instanceof PackageRegistry) {
        (new AdminPackages($authentication, $packageRegistry, new RequiredPackagePolicy(['system.themes']), $themeResolver))
            ->register($router);
    }
}

$router->add(
    'GET',
    '/login',
    'core.login',
    static function (Request $_) use ($themeResolver, $authentication, $providers): Response {
        if ($authentication === null) {
            return Response::text('Authentication is not configured.', 503)->withPrivateNoStore();
        }
        if ($authentication->current() !== null) {
            return Response::redirect('/admin');
        }
        $actions = array_map(
            static fn ($provider): PageAction => new PageAction(
                'login-' . $provider->name(),
                'Zaloguj przez ' . $provider->label(),
                ActionIntent::Navigate,
                '/auth/' . rawurlencode($provider->name()),
            ),
            $providers->all(),
        );
        $page = new PageDefinition('login', 'Logowanie', 'public', [PageRegion::CONTENT => [
            new Card([
                new Heading('Wybierz dostawcę tożsamości', 2),
                new Text('miniPORTAL nie przechowuje hasła administratora. Logowanie odbywa się przez bezpieczny przepływ OAuth/OIDC.'),
            ], 'Panel administracyjny'),
        ]], [new Breadcrumb('Start', '/'), new Breadcrumb('Logowanie')], $actions);
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page))->withPrivateNoStore();
    },
);

$router->add(
    'GET',
    '/auth/{provider}',
    'core.auth.start',
    static function (Request $request) use ($oauth, $logger, $runtime): Response {
        if ($oauth === null) {
            return Response::text('Authentication is not configured.', 503)->withPrivateNoStore();
        }
        try {
            return Response::externalRedirect($oauth->start($request->attribute('provider') ?? ''));
        } catch (OAuthRateLimitExceeded) {
            return new Response('Too many authentication attempts.', 429, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Retry-After' => '600',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (InvalidArgumentException) {
            return Response::text('Identity provider not found.', 404)->withPrivateNoStore();
        }
    },
);

$router->add(
    'GET',
    '/auth/{provider}/callback',
    'core.auth.callback',
    static function (Request $request) use ($oauth): Response {
        if ($oauth === null) {
            return Response::text('Authentication is not configured.', 503)->withPrivateNoStore();
        }
        try {
            $authenticated = $oauth->complete(
                $request->attribute('provider') ?? '',
                $request->query['state'] ?? null,
                $request->query['code'] ?? null,
            );
        } catch (OAuthRateLimitExceeded) {
            return new Response('Too many authentication attempts.', 429, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Retry-After' => '600',
                'Cache-Control' => 'private, no-store',
            ]);
        } catch (Throwable $exception) {
            $logger->error('External authentication callback failed.', [
                'provider' => $request->attribute('provider'),
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
                'correlation_id' => (string) $runtime->correlationId,
            ]);
            return Response::text('External authentication failed.', 502)->withPrivateNoStore();
        }
        if ($authenticated) {
            return Response::redirect('/admin');
        }
        return Response::text('External identity is not permitted or the login request expired.', 403)->withPrivateNoStore();
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
    static function (Request $request) use ($themeResolver, $runtime, $widgetComposer): Response {
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
                ]), $widgetComposer instanceof WidgetComposer
                    ? $widgetComposer->slot('home', 'home.after_content', $request->context)
                    : new \SyntaxDevTeam\MiniPortal\UI\Component\WidgetSlot('home.after_content')],
                PageRegion::ASIDE => [new Heading('Stan prac', 2), new Text('UI API i Base Theme'), new Text('Plasma Theme: pierwszy szkielet'), new Text('Request: ' . (string) $runtime->correlationId, TextTone::Muted)],
                PageRegion::FOOTER => [new Text('SyntaxDevTeam · miniPORTAL 1.0', TextTone::Muted)],
            ],
            [new Breadcrumb('Start')],
            [new PageAction('open-admin', 'Otwórz panel', ActionIntent::Navigate, '/admin')],
        );
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page));
    },
);

$themeModuleAvailable = false;
$registry = $services->get(PackageRegistry::class);
if ($registry instanceof PackageRegistry) {
    $mount = new ActiveModuleMount($registry, $router, $logger, $widgetCatalog instanceof WidgetCatalog ? $widgetCatalog : null,
        $apiGateway instanceof ServiceApiGateway ? $apiGateway : null);
    $capabilities = $services->get(CapabilityRegistry::class);
    $results = (new ActiveModuleLoader($registry, $mount, $logger,
        $capabilities instanceof CapabilityRegistry ? $capabilities->versions() : []))->mountAll(
        new ModuleContext($runtime->correlationId),
        ['system.themes' => new SystemThemesModule($themeResolver)],
    );
    $themeModuleAvailable = $results['system.themes']->successful ?? false;
}

$router->add(
    'GET',
    '/admin',
    'core.admin',
    static function (Request $_) use ($themeResolver, $authentication, $themeModuleAvailable): Response {
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
            [
                new PageAction('refresh', 'Odśwież', ActionIntent::Refresh),
                new PageAction('users', 'Użytkownicy', ActionIntent::Navigate, '/admin/users'),
                new PageAction('modules', 'Moduły i pakiety', ActionIntent::Navigate, '/admin/modules'),
                ...($themeModuleAvailable ? [new PageAction('themes', 'Szablony', ActionIntent::Navigate, '/modules/system.themes')] : []),
            ],
        );
        return Response::html($themeResolver->resolve('plasma', $page->layoutRole)->theme->render($page))->withPrivateNoStore();
    },
);

$session = $authentication?->current();
$request = Request::fromGlobals()->withContext($contextFactory->create(
    principalId: $session?->principalId,
    locale: 'pl',
    timezone: 'Europe/Warsaw',
    permissions: $session?->permissions ?? [],
    csrfToken: $session?->csrfToken,
));
$router->handle($request)->send();
