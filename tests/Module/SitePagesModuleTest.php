<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Module;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleIdentity;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Module\ActiveModuleMount;
use SyntaxDevTeam\MiniPortal\Core\Navigation\NavigationCatalog;
use SyntaxDevTeam\MiniPortal\Core\Navigation\BufferedNavigationRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArraySessionStore;
use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Module\SitePages\SitePageRepository;
use SyntaxDevTeam\MiniPortal\Module\SitePages\SitePagesModule;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;
use SyntaxDevTeam\MiniPortal\UI\UiFacade;

final class SitePagesModuleTest extends TestCase
{
    public function testNavigationCannotEscapeModuleMount(): void
    {
        $registrar = new BufferedNavigationRegistrar('site.pages');
        $this->expectException(\InvalidArgumentException::class);
        $registrar->add('escape', 'Escape', '/../admin');
    }

    public function testAuthoringRequiresSessionCsrfAndKeepsDraftPrivate(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $plan = (new MigrationPlanner($database, $ledger))->plan('site.pages', SitePagesModule::migrations());
        (new MigrationRunner($database, $ledger))->apply($plan);
        $pages = new SitePageRepository($database);
        $auth = new AuthenticationManager(new AuthenticationSettings([
            new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
        ], ['github:owner']), new ArraySessionStore(), new FrozenClock(new DateTimeImmutable('2026-01-01T12:00:00Z')));
        $registry = new InMemoryPackageRegistry();
        $path = dirname(__DIR__, 2) . '/modules/SitePages';
        $registry->add(new PackageRelease((new ManifestParser())->parseFile($path . '/manifest.json'),
            $path, PackageState::Active));
        $registry->setActive('site.pages', '1.0.0');
        $router = new Router();
        $navigation = new NavigationCatalog($registry);
        $module = new SitePagesModule($pages, new UiFacade(new ThemeResolver(new BaseTheme(), '1.0.0')),
            new ModuleIdentity($auth));
        self::assertTrue((new ActiveModuleMount($registry, $router, new InMemoryLogger(), navigation: $navigation))
            ->mount('site.pages', $module, new ModuleContext(CorrelationId::generate()))?->successful);
        self::assertCount(1, $navigation->actions('public'));
        self::assertSame('/modules/site.pages', $navigation->actions('public')[0]->url);

        $base = '/modules/site.pages';
        self::assertSame(403, $router->handle(new Request('GET', $base . '/admin'))->status);
        self::assertTrue($auth->login(new UserAccount(str_repeat('a', 32), 'Owner', null, null,
            AccountStatus::Active, ['owner'], ['*'])));
        self::assertSame(403, $router->handle(new Request('POST', $base . '/admin/new',
            form: ['_token' => 'wrong']))->status);
        $form = [
            '_token' => $auth->csrfToken(), 'title' => 'Kontakt', 'slug' => 'kontakt',
            'summary' => 'Skontaktuj się', 'content_format' => 'markdown',
            'content' => "## Witaj\n\n<script>alert(1)</script>", 'status' => 'published',
        ];
        self::assertSame(303, $router->handle(new Request('POST', $base . '/admin/new', form: $form))->status);
        self::assertCount(1, $pages->listing());
        $view = $router->handle(new Request('GET', $base . '/kontakt'));
        self::assertSame(200, $view->status);
        self::assertStringContainsString('Skontaktuj się', $view->body);
        self::assertStringNotContainsString('<script>', $view->body);
        $id = $pages->listing()[0]->id;
        self::assertSame(303, $router->handle(new Request('POST', $base . '/admin/' . $id . '/edit',
            form: [...$form, 'status' => 'draft']))->status);
        self::assertSame(404, $router->handle(new Request('GET', $base . '/kontakt'))->status);
        self::assertSame(200, $router->handle(new Request('GET', $base . '/admin/' . $id . '/edit'))->status);
        (new PackageLifecycleManager($registry, new PackageLifecycle()))
            ->transition('site.pages', '1.0.0', PackageState::Disabled);
        self::assertSame([], $navigation->actions('public'));
        self::assertSame(404, $router->handle(new Request('GET', $base))->status);
    }
}
