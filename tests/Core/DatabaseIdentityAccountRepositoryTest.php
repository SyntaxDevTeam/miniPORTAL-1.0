<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use DateTimeImmutable;
use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class DatabaseIdentityAccountRepositoryTest extends TestCase
{
    private Database $database;
    private DatabaseIdentityAccountRepository $repository;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $migration = DatabaseIdentityMigration::definition();
        $ledger = new DatabaseMigrationLedger($this->database);
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(DatabaseIdentityMigration::OWNER_ID, [$migration]);
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $this->repository = new DatabaseIdentityAccountRepository(
            $this->database,
            new FrozenClock(new DateTimeImmutable('2026-09-26T12:00:00Z')),
        );
    }

    public function testFirstIdentityBecomesOwnerAndSubsequentIdentityIsPending(): void
    {
        $owner = $this->repository->resolve(new ExternalIdentity(
            'github', '123', 'Owner', 'owner@example.test', true,
        ));
        $pending = $this->repository->resolve(new ExternalIdentity(
            'discord', '456', 'Candidate', 'candidate@example.test', true,
        ));

        self::assertSame(AccountStatus::Active, $owner->status);
        self::assertSame(['owner'], $owner->roles);
        self::assertSame(['*'], $owner->permissions);
        self::assertTrue($owner->canAccessAdmin());
        self::assertSame(AccountStatus::Pending, $pending->status);
        self::assertSame(['user'], $pending->roles);
        self::assertSame([], $pending->permissions);
        self::assertFalse($pending->canAccessAdmin());
    }

    public function testRepeatedIdentityResolvesTheSameLocalAccount(): void
    {
        $first = $this->repository->resolve(new ExternalIdentity('github', '123', 'First name'));
        $second = $this->repository->resolve(new ExternalIdentity('github', '123', 'Updated name'));

        self::assertSame($first->id, $second->id);
        self::assertSame(['owner'], $second->roles);
    }

    public function testMigrationIsOwnedAndReversible(): void
    {
        $migration = DatabaseIdentityMigration::definition();
        self::assertSame('core.security', $migration->ownerId);
        self::assertTrue($migration->reversible());
    }
}
