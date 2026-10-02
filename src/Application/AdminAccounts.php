<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Application;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountLifecycleDenied;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountNotFound;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticatedSession;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\AccountDirectory;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\AccountLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\EmptyState;
use SyntaxDevTeam\MiniPortal\UI\Component\Form;
use SyntaxDevTeam\MiniPortal\UI\Component\Pagination;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

/** HTTP composition for Core-owned account administration; all data comes from public contracts. */
final readonly class AdminAccounts
{
    public function __construct(
        private AuthenticationManager $authentication,
        private AccountDirectory $directory,
        private AccountLifecycle $lifecycle,
        private ThemeResolver $themes,
        private Logger $logger,
    ) {
    }

    public function register(Router $router): void
    {
        $router->add('GET', '/admin/users', 'core.admin.users', $this->index(...));
        $router->add('POST', '/admin/users/{id}/activate', 'core.admin.users.activate',
            fn (Request $request): Response => $this->changeStatus($request, AccountStatus::Active));
        $router->add('POST', '/admin/users/{id}/block', 'core.admin.users.block',
            fn (Request $request): Response => $this->changeStatus($request, AccountStatus::Blocked));
    }

    private function index(Request $request): Response
    {
        $session = $this->authentication->current();
        if ($session === null) {
            return Response::redirect('/login');
        }
        if (!$this->hasPermission($session, 'users.view')) {
            return Response::text('Access denied.', 403)->withPrivateNoStore();
        }
        $rawPage = $request->query['page'] ?? '1';
        if (preg_match('/^[1-9][0-9]{0,3}$/D', $rawPage) !== 1) {
            return Response::text('Invalid page.', 400)->withPrivateNoStore();
        }
        $pageNumber = (int) $rawPage;
        $accounts = $this->directory->listing(21, ($pageNumber - 1) * 20);
        $hasNext = count($accounts) > 20;
        $accounts = array_slice($accounts, 0, 20);
        if ($accounts === [] && $pageNumber > 1) {
            return Response::redirect('/admin/users');
        }
        $components = [];
        if (($request->query['updated'] ?? null) === '1') {
            $components[] = new Alert('Status konta został zmieniony.', AlertSeverity::Success);
        }
        foreach ($accounts as $account) {
            $components[] = $this->accountCard($account, $session);
        }
        if ($accounts === []) {
            $components[] = new EmptyState('Brak kont', 'Nowe konta pojawią się po pierwszym logowaniu przez dostawcę tożsamości.');
        }
        if ($pageNumber > 1 || $hasNext) {
            $components[] = new Pagination(
                $pageNumber,
                $hasNext ? $pageNumber + 1 : $pageNumber,
                $pageNumber > 1 ? '/admin/users?page=' . ($pageNumber - 1) : null,
                $hasNext ? '/admin/users?page=' . ($pageNumber + 1) : null,
            );
        }
        $page = new PageDefinition(
            'admin-users',
            'Użytkownicy',
            'dashboard',
            [PageRegion::CONTENT => $components],
            [new Breadcrumb('Panel', '/admin'), new Breadcrumb('Użytkownicy')],
            [new PageAction('back', 'Wróć do panelu', ActionIntent::Navigate, '/admin')],
        );
        return Response::html($this->themes->resolve('plasma', $page->layoutRole)->theme->render($page))
            ->withPrivateNoStore();
    }

    private function accountCard(UserAccount $account, AuthenticatedSession $session): Card
    {
        $content = [
            new Text('Status: ' . $account->status->value),
            new Text('Role: ' . implode(', ', $account->roles)),
        ];
        if ($account->email !== null) {
            $content[] = new Text('E-mail: ' . $account->email);
        }
        $mayManage = $this->hasPermission($session, 'users.manage');
        $mayManageOwner = !in_array('owner', $account->roles, true)
            || in_array('*', $session->permissions, true);
        if ($mayManage && $mayManageOwner && $account->id !== $session->principalId) {
            $action = $account->status === AccountStatus::Active ? 'block' : 'activate';
            $content[] = new Form(
                '/admin/users/' . $account->id . '/' . $action,
                FormMethod::Post,
                [],
                $action === 'block' ? 'Zablokuj konto' : 'Aktywuj konto',
                $session->csrfToken,
            );
        }
        return new Card($content, $account->displayName);
    }

    private function changeStatus(Request $request, AccountStatus $status): Response
    {
        $session = $this->authentication->current();
        if ($session === null) {
            return Response::redirect('/login');
        }
        if (!$this->hasPermission($session, 'users.manage')) {
            return Response::text('Access denied.', 403)->withPrivateNoStore();
        }
        $token = $request->formValue('_token');
        if ($token === null || !hash_equals($session->csrfToken, $token)) {
            return Response::text('Invalid CSRF token.', 403)->withPrivateNoStore();
        }
        $id = $request->attribute('id') ?? '';
        $target = $this->directory->find($id);
        if ($target === null) {
            return Response::text('Account not found.', 404)->withPrivateNoStore();
        }
        if ($id === $session->principalId) {
            return Response::text('Cannot change your own account status here.', 403)->withPrivateNoStore();
        }
        if (in_array('owner', $target->roles, true) && !in_array('*', $session->permissions, true)) {
            return Response::text('Access denied.', 403)->withPrivateNoStore();
        }
        try {
            $this->lifecycle->changeStatus(
                $id,
                $status,
                'user:' . $session->principalId,
                ($request->context === null ? (string) CorrelationId::generate() : (string) $request->context->correlationId),
            );
        } catch (AccountNotFound) {
            return Response::text('Account not found.', 404)->withPrivateNoStore();
        } catch (AccountLifecycleDenied) {
            return Response::text('Account status change was denied.', 409)->withPrivateNoStore();
        } catch (\Throwable $exception) {
            $errorId = (string) CorrelationId::generate();
            $this->logger->error('Account status change failed.', [
                'error_id' => $errorId,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            return Response::text('Account status change failed. Error ID: ' . $errorId, 503)
                ->withPrivateNoStore();
        }
        return Response::redirect('/admin/users?updated=1');
    }

    private function hasPermission(AuthenticatedSession $session, string $permission): bool
    {
        return in_array('*', $session->permissions, true)
            || in_array($permission, $session->permissions, true);
    }
}
