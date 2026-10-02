<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Application;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Application\AdminAccounts;
use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\ArraySessionStore;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseAccountLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\InMemoryAuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\ScopedAuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

final class AdminAccountsTest extends TestCase
{
    private Database $database;
    private DatabaseIdentityAccountRepository $accounts;
    private AuthenticationManager $authentication;
    private Router $router;
    private InMemoryAuditSink $audit;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $clock = new FrozenClock(new DateTimeImmutable('2026-10-02T12:00:00Z'));
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($this->database);
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(
            DatabaseIdentityMigration::OWNER_ID, [DatabaseIdentityMigration::definition()],
        );
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $this->accounts = new DatabaseIdentityAccountRepository($this->database, $clock);
        $this->audit = new InMemoryAuditSink();
        $this->authentication = new AuthenticationManager(
            new AuthenticationSettings([
                new IdentityProviderSettings('github', 'client', 'secret', 'https://portal.test/auth/github/callback'),
            ], [], 1800, 28800),
            new ArraySessionStore(),
            $clock,
            $this->accounts,
        );
        $this->router = new Router();
        (new AdminAccounts(
            $this->authentication,
            $this->accounts,
            new DatabaseAccountLifecycle($this->database, new ScopedAuditTrail('core.security', $this->audit, $clock)),
            new ThemeResolver(new BaseTheme(), '1.0.0'),
            new InMemoryLogger(),
        ))->register($this->router);
    }

    public function testOwnerCanSeePendingAccountAndActivateOnlyWithCsrf(): void
    {
        $owner = $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));
        $candidate = $this->accounts->resolve(new ExternalIdentity('discord', 'candidate', '<Candidate>'));
        self::assertSame(303, $this->router->handle(new Request('GET', '/admin/users'))->status);
        self::assertTrue($this->authentication->login($owner));

        $page = $this->router->handle(new Request('GET', '/admin/users'));
        self::assertSame(200, $page->status);
        self::assertStringContainsString('&lt;Candidate&gt;', $page->body);
        self::assertStringNotContainsString('<Candidate>', $page->body);
        self::assertStringContainsString('/admin/users/' . $candidate->id . '/activate', $page->body);
        self::assertSame('private, no-store', $page->headers['Cache-Control']);

        $url = '/admin/users/' . $candidate->id . '/activate';
        self::assertSame(403, $this->router->handle(new Request('POST', $url, form: ['_token' => 'bad']))->status);
        self::assertSame(AccountStatus::Pending, $this->accounts->find($candidate->id)?->status);
        $response = $this->router->handle(new Request('POST', $url, form: [
            '_token' => $this->authentication->csrfToken(),
        ]));
        self::assertSame(303, $response->status);
        self::assertSame(AccountStatus::Active, $this->accounts->find($candidate->id)?->status);
        self::assertCount(1, $this->audit->events());
    }

    public function testBlockedAccountSessionIsInvalidatedOnNextRequest(): void
    {
        $owner = $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));
        self::assertTrue($this->authentication->login($owner));
        $table = (new StorageNamespace(DatabaseIdentityMigration::OWNER_ID))->table('users')->value;
        $this->database->execute(new SqlStatement(sprintf(
            'UPDATE %s SET status = :status WHERE id = :id', $table,
        ), ['status' => 'blocked', 'id' => $owner->id]));
        self::assertSame(303, $this->router->handle(new Request('GET', '/admin/users'))->status);
        self::assertNull($this->authentication->current());
    }

    public function testOwnerCannotBlockSelfThroughStatusRoute(): void
    {
        $owner = $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));
        self::assertTrue($this->authentication->login($owner));
        $response = $this->router->handle(new Request('POST', '/admin/users/' . $owner->id . '/block', form: [
            '_token' => $this->authentication->csrfToken(),
        ]));
        self::assertSame(403, $response->status);
        self::assertSame(AccountStatus::Active, $this->accounts->find($owner->id)?->status);
    }
}
