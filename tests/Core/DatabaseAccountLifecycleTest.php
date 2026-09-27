<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountLifecycleDenied;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountNotFound;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseAccountLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\InMemoryAuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\ScopedAuditTrail;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class DatabaseAccountLifecycleTest extends TestCase
{
    private Database $database;
    private DatabaseIdentityAccountRepository $accounts;
    private DatabaseAccountLifecycle $lifecycle;
    private InMemoryAuditSink $auditSink;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $clock = new FrozenClock(new DateTimeImmutable('2026-09-27T12:00:00Z'));
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $migration = DatabaseIdentityMigration::definition();
        $ledger = new DatabaseMigrationLedger($this->database);
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(DatabaseIdentityMigration::OWNER_ID, [$migration]);
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $this->accounts = new DatabaseIdentityAccountRepository($this->database, $clock);
        $this->auditSink = new InMemoryAuditSink();
        $this->lifecycle = new DatabaseAccountLifecycle(
            $this->database,
            new ScopedAuditTrail('core.security', $this->auditSink, $clock),
        );
    }

    public function testActivatesPendingAccountAndAuditsMutation(): void
    {
        $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));
        $candidate = $this->accounts->resolve(new ExternalIdentity('discord', 'candidate', 'Candidate'));

        $this->lifecycle->changeStatus($candidate->id, AccountStatus::Active, 'user:owner', 'request-1234');

        $resolved = $this->accounts->resolve(new ExternalIdentity('discord', 'candidate', 'Candidate'));
        self::assertSame(AccountStatus::Active, $resolved->status);
        self::assertSame(AuditResult::Succeeded, $this->auditSink->events()[0]->result);
        self::assertSame(['from' => 'pending', 'to' => 'active'], $this->auditSink->events()[0]->context);
    }

    public function testBlocksAndReactivatesAccount(): void
    {
        $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));
        $candidate = $this->accounts->resolve(new ExternalIdentity('discord', 'candidate', 'Candidate'));
        $this->lifecycle->changeStatus($candidate->id, AccountStatus::Active, 'user:owner', 'request-1234');

        $this->lifecycle->changeStatus($candidate->id, AccountStatus::Blocked, 'user:owner', 'request-1235');
        self::assertSame(AccountStatus::Blocked, $this->accounts->resolve(
            new ExternalIdentity('discord', 'candidate', 'Candidate'),
        )->status);
        $this->lifecycle->changeStatus($candidate->id, AccountStatus::Active, 'user:owner', 'request-1236');
        self::assertSame(AccountStatus::Active, $this->accounts->resolve(
            new ExternalIdentity('discord', 'candidate', 'Candidate'),
        )->status);
    }

    public function testCannotBlockLastActiveOwnerAndAuditsDenial(): void
    {
        $owner = $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));

        try {
            $this->lifecycle->changeStatus($owner->id, AccountStatus::Blocked, 'user:owner', 'request-1234');
            self::fail('The last active Owner should remain active.');
        } catch (AccountLifecycleDenied) {
            self::assertSame(AccountStatus::Active, $this->accounts->resolve(
                new ExternalIdentity('github', 'owner', 'Owner'),
            )->status);
            self::assertSame(AuditResult::Denied, $this->auditSink->events()[0]->result);
            self::assertSame(['from' => 'active', 'to' => 'blocked'], $this->auditSink->events()[0]->context);
        }
    }

    public function testCannotReturnAccountToPending(): void
    {
        $owner = $this->accounts->resolve(new ExternalIdentity('github', 'owner', 'Owner'));

        $this->expectException(AccountLifecycleDenied::class);
        $this->lifecycle->changeStatus($owner->id, AccountStatus::Pending, 'user:owner', 'request-1234');
    }

    public function testMissingAccountIsDeniedAndAudited(): void
    {
        try {
            $this->lifecycle->changeStatus(str_repeat('f', 32), AccountStatus::Active, 'user:owner', 'request-1234');
            self::fail('Missing account should not be changed.');
        } catch (AccountNotFound) {
            self::assertSame(AuditResult::Denied, $this->auditSink->events()[0]->result);
        }
    }
}
