<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Migration;

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
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Migration\Legacy\IdentityImporter;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class IdentityImporterTest extends TestCase
{
    private Database $database;
    private IdentityImporter $importer;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($this->database);
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(
            DatabaseIdentityMigration::OWNER_ID, [DatabaseIdentityMigration::definition()],
        );
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $this->importer = new IdentityImporter($this->database);
    }

    public function testPlanDoesNotWriteAndApplyPreservesIdentitiesAndOwnerBootstrap(): void
    {
        $snapshot = $this->snapshot();
        $plan = $this->importer->plan($snapshot);
        self::assertSame(2, $plan->users);
        self::assertSame(2, $plan->identities);
        $users = (new StorageNamespace(DatabaseIdentityMigration::OWNER_ID))->table('users')->value;
        $count = $this->database->fetchOne(new SqlStatement(sprintf('SELECT COUNT(*) AS total FROM %s', $users)));
        self::assertNotNull($count);
        self::assertArrayHasKey('total', $count);
        self::assertTrue(is_numeric($count['total']));
        self::assertSame(0, (int) $count['total']);

        $this->importer->apply($snapshot, $plan->checksum);
        $accounts = new DatabaseIdentityAccountRepository(
            $this->database, new FrozenClock(new DateTimeImmutable('2026-10-02T12:00:00Z')),
        );
        $owner = $accounts->resolve(new ExternalIdentity('github', 'old-owner', 'Owner'));
        self::assertSame(IdentityImporter::newId(12), $owner->id);
        self::assertSame(AccountStatus::Active, $owner->status);
        self::assertContains('*', $owner->permissions);
        $candidate = $accounts->resolve(new ExternalIdentity('discord', 'old-user', 'User'));
        self::assertSame(IdentityImporter::newId(19), $candidate->id);
        self::assertSame(AccountStatus::Active, $candidate->status);
        $new = $accounts->resolve(new ExternalIdentity('google', 'new-user', 'New'));
        self::assertSame(AccountStatus::Pending, $new->status);
    }

    public function testWrongChecksumOrNonEmptyTargetBlocksImport(): void
    {
        $snapshot = $this->snapshot();
        try {
            $this->importer->apply($snapshot, str_repeat('0', 64));
            self::fail('Wrong checksum must be rejected.');
        } catch (\LogicException) {
            self::assertSame(2, $this->importer->plan($snapshot)->users);
        }
        $this->importer->apply($snapshot, hash('sha256', $snapshot));
        $this->expectException(\LogicException::class);
        $this->importer->plan($snapshot);
    }

    public function testDanglingIdentityIsRejectedBeforeWriting(): void
    {
        $snapshot = str_replace('"user_id":19', '"user_id":999', $this->snapshot());
        $this->expectException(\InvalidArgumentException::class);
        $this->importer->plan($snapshot);
    }

    private function snapshot(): string
    {
        $now = '2026-09-26 12:00:00';
        return json_encode([
            'schema' => 1,
            'timezone' => 'Europe/Warsaw',
            'users' => [
                ['id' => 12, 'display_name' => 'Owner', 'email' => 'owner@example.test', 'avatar_url' => null,
                    'status' => 'active', 'created_at' => $now, 'last_login_at' => $now],
                ['id' => 19, 'display_name' => 'User', 'email' => null, 'avatar_url' => null,
                    'status' => 'active', 'created_at' => $now, 'last_login_at' => null],
            ],
            'identities' => [
                ['user_id' => 12, 'provider' => 'github', 'provider_subject' => 'old-owner',
                    'provider_login' => 'Owner', 'provider_email' => 'owner@example.test',
                    'email_verified' => 1, 'linked_at' => $now, 'last_used_at' => $now],
                ['user_id' => 19, 'provider' => 'discord', 'provider_subject' => 'old-user',
                    'provider_login' => 'User', 'provider_email' => null,
                    'email_verified' => 0, 'linked_at' => $now, 'last_used_at' => null],
            ],
            'roles' => [['name' => 'owner', 'label' => 'Owner'], ['name' => 'user', 'label' => 'User']],
            'permissions' => [['name' => '*', 'label' => 'All'], ['name' => 'users.view', 'label' => 'View users']],
            'user_roles' => [['user_id' => 12, 'role_name' => 'owner'], ['user_id' => 19, 'role_name' => 'user']],
            'role_permissions' => [['role_name' => 'owner', 'permission_name' => '*']],
        ], JSON_THROW_ON_ERROR);
    }
}
