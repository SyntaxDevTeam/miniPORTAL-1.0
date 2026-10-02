<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\SitePages;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleFactory;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleIdentity;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleMigrationProvider;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleServices;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Component\RichText;
use SyntaxDevTeam\MiniPortal\UI\Component\SelectField;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Component\TextAreaField;
use SyntaxDevTeam\MiniPortal\UI\Component\TextField;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\ContentFormat;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\UiFacade;

/** Optional page authoring module; no domain logic is stored in Core. */
final readonly class SitePagesModule implements Module, ModuleFactory, ModuleMigrationProvider
{
    private const BASE = '/modules/site.pages';

    public function __construct(
        private SitePageRepository $pages,
        private UiFacade $ui,
        private ?ModuleIdentity $identity,
    ) {
    }

    public static function create(ModuleServices $services): Module
    {
        if ($services->database === null) {
            throw new \RuntimeException('Pages module requires the storage capability.');
        }
        return new self(new SitePageRepository($services->database), $services->ui, $services->identity);
    }

    public static function migrations(): array
    {
        return [SitePageRepository::migration()];
    }

    public function register(ModuleRegistration $registration): void
    {
        $registration->navigation?->add('pages', 'Strony', '/', 'public');
        $registration->navigation?->add('manage-pages', 'Zarządzaj stronami', '/admin', 'admin');
        $registration->routes->get('/admin', 'admin', $this->admin(...));
        $registration->routes->get('/admin/new', 'new', $this->new(...));
        $registration->routes->post('/admin/new', 'create', $this->createPage(...));
        $registration->routes->get('/admin/{id}/edit', 'edit', $this->edit(...));
        $registration->routes->post('/admin/{id}/edit', 'update', $this->update(...));
        $registration->routes->get('/', 'index', $this->index(...));
        $registration->routes->get('/{slug}', 'view', $this->view(...));
    }

    public function boot(ModuleContext $context): void
    {
    }

    private function index(Request $_): Response
    {
        $pages = $this->pages->listing();
        $cards = $pages === [] ? [new EmptyState('Brak stron', 'Opublikowane strony pojawią się tutaj.')]
            : array_map(static fn (SitePage $page): Card => new Card([
                new Text($page->summary !== '' ? $page->summary : $page->slug),
            ], $page->title), $pages);
        $actions = array_map(static fn (SitePage $page): PageAction => new PageAction(
            'page-' . $page->id, $page->title, ActionIntent::Navigate, self::BASE . '/' . $page->slug), $pages);
        $definition = new PageDefinition('site-pages', 'Strony', 'public',
            [PageRegion::CONTENT => $cards], [new Breadcrumb('Start', '/'), new Breadcrumb('Strony')], $actions);
        return Response::html($this->ui->render($definition));
    }

    private function view(Request $request): Response
    {
        $page = $this->pages->findBySlug($request->attribute('slug') ?? '');
        if ($page === null) {
            return Response::text('Not Found', 404);
        }
        $components = [];
        if ($page->summary !== '') {
            $components[] = new Text($page->summary);
        }
        $components[] = new RichText($page->content, $page->format);
        $definition = new PageDefinition('site-page-' . $page->id, $page->title, 'public',
            [PageRegion::CONTENT => $components],
            [new Breadcrumb('Start', '/'), new Breadcrumb('Strony', self::BASE), new Breadcrumb($page->title)]);
        return Response::html($this->ui->render($definition));
    }

    private function admin(Request $_): Response
    {
        if (!$this->mayManage()) {
            return $this->denied();
        }
        $pages = $this->pages->listing(false);
        $cards = $pages === [] ? [new EmptyState('Brak stron', 'Dodaj pierwszą stronę.')]
            : array_map(static fn (SitePage $page): Card => new Card([
                new Text('Status: ' . $page->status . ' · /' . $page->slug),
            ], $page->title), $pages);
        $actions = [new PageAction('add-page', 'Dodaj stronę', ActionIntent::Navigate, self::BASE . '/admin/new')];
        foreach ($pages as $page) {
            $actions[] = new PageAction('edit-' . $page->id, 'Edytuj: ' . $page->title,
                ActionIntent::Navigate, self::BASE . '/admin/' . $page->id . '/edit');
        }
        $definition = new PageDefinition('site-pages-admin', 'Zarządzanie stronami', 'dashboard',
            [PageRegion::CONTENT => $cards], [new Breadcrumb('Panel', '/admin'), new Breadcrumb('Strony')], $actions);
        return Response::html($this->ui->render($definition))->withPrivateNoStore();
    }

    private function new(Request $_): Response
    {
        return $this->mayManage() ? $this->editor(null) : $this->denied();
    }

    private function edit(Request $request): Response
    {
        if (!$this->mayManage()) {
            return $this->denied();
        }
        $page = $this->pages->findById($request->attribute('id') ?? '');
        return $page === null ? Response::text('Not Found', 404)->withPrivateNoStore() : $this->editor($page);
    }

    private function editor(?SitePage $page, ?string $error = null): Response
    {
        $session = $this->identity?->current();
        if ($session === null) {
            return $this->denied();
        }
        $action = $page === null ? self::BASE . '/admin/new' : self::BASE . '/admin/' . $page->id . '/edit';
        $content = [];
        if ($error !== null) {
            $content[] = new Alert($error, AlertSeverity::Error);
        }
        $content[] = new Card([new Form($action, FormMethod::Post, [
            new TextField('title', 'Tytuł', value: $page?->title, required: true),
            new TextField('slug', 'Slug', value: $page?->slug, required: true),
            new TextAreaField('summary', 'Zajawka', $page?->summary, rows: 3),
            new SelectField('content_format', 'Format', ['markdown' => 'Markdown', 'html' => 'HTML'],
                $page?->format->value ?? 'markdown'),
            new TextAreaField('content', 'Treść', $page?->content, true, rows: 20),
            new SelectField('status', 'Status', ['draft' => 'Szkic', 'published' => 'Opublikowana'],
                $page === null ? 'draft' : $page->status),
        ], 'Zapisz stronę', $session->csrfToken)], 'Edycja strony');
        $definition = new PageDefinition('site-page-editor', $page === null ? 'Nowa strona' : 'Edytuj stronę',
            'dashboard', [PageRegion::CONTENT => $content],
            [new Breadcrumb('Panel', '/admin'), new Breadcrumb('Strony', self::BASE . '/admin'), new Breadcrumb('Edytor')]);
        return Response::html($this->ui->render($definition))->withPrivateNoStore();
    }

    private function createPage(Request $request): Response
    {
        return $this->save($request, null);
    }

    private function update(Request $request): Response
    {
        $page = $this->pages->findById($request->attribute('id') ?? '');
        return $page === null ? Response::text('Not Found', 404)->withPrivateNoStore() : $this->save($request, $page);
    }

    private function save(Request $request, ?SitePage $current): Response
    {
        $identity = $this->identity;
        if ($identity === null) {
            return $this->denied();
        }
        $session = $identity->current();
        if (!$this->mayManage() || $session === null) {
            return $this->denied();
        }
        if (!$identity->verifyCsrf($request->formValue('_token'))) {
            return Response::text('Invalid CSRF token.', 403)->withPrivateNoStore();
        }
        $title = trim($request->formValue('title') ?? '');
        $slug = trim($request->formValue('slug') ?? '');
        $summary = trim($request->formValue('summary') ?? '');
        $content = trim($request->formValue('content') ?? '');
        $format = ContentFormat::tryFrom($request->formValue('content_format') ?? '');
        $status = $request->formValue('status') ?? '';
        if ($title === '' || mb_strlen($title) > 180 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug) !== 1
            || strlen($slug) > 191 || mb_strlen($summary) > 2000 || $content === '' || strlen($content) > 500_000
            || $format === null || !in_array($status, ['draft', 'published'], true)) {
            return Response::text('Invalid page fields.', 422)->withPrivateNoStore();
        }
        $sameSlug = $this->pages->findBySlug($slug, false);
        if ($sameSlug !== null && $sameSlug->id !== $current?->id) {
            return Response::text('Slug is already in use.', 409)->withPrivateNoStore();
        }
        $id = $current === null ? bin2hex(random_bytes(16)) : $current->id;
        $page = new SitePage($id, $slug, $title,
            $summary, $content, $format, $status,
            $current === null ? $session->principalId : $current->authorId,
            $current?->legacyId);
        $this->pages->save($page);
        return Response::redirect(self::BASE . '/admin')->withPrivateNoStore();
    }

    private function mayManage(): bool
    {
        $session = $this->identity?->current();
        return $session !== null && (in_array('*', $session->permissions, true)
            || in_array('pages.manage', $session->permissions, true));
    }

    private function denied(): Response
    {
        return Response::text('Access denied.', 403)->withPrivateNoStore();
    }
}
