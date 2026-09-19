<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Storage;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\Contract\MigrationPreflightCheck;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationBlocked;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationExecutionPolicy;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationFailed;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPhase;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPreflightResult;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;

final class MigrationEngineTest extends TestCase
{
    private Database $database;
    private DatabaseMigrationLedger $ledger;
    private MigrationPlanner $planner;
    private MigrationRunner $runner;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }

        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $this->ledger = new DatabaseMigrationLedger($this->database);
        $this->planner = new MigrationPlanner($this->database, $this->ledger);
        $this->runner = new MigrationRunner($this->database, $this->ledger);
    }

    public function testPlanningDoesNotExecuteMigrationAndApplyingRecordsIt(): void
    {
        $migration = $this->createTableMigration();
        $plan = $this->planner->plan('fixture.package', [$migration]);

        self::assertTrue($plan->executable());
        self::assertCount(1, $plan->pending());
        self::assertNull($this->table('fixture_items'));

        $result = $this->runner->apply($plan);

        self::assertSame(['001-create-items'], $result->appliedMigrationIds);
        self::assertNotNull($this->table('fixture_items'));
        self::assertCount(1, $this->ledger->records('fixture.package'));

        $nextPlan = $this->planner->plan('fixture.package', [$migration]);
        self::assertTrue($nextPlan->executable());
        self::assertSame([], $nextPlan->pending());
    }

    public function testChangedAppliedMigrationIsBlockedByChecksumDrift(): void
    {
        $migration = $this->createTableMigration();
        $this->runner->apply($this->planner->plan('fixture.package', [$migration]));

        $changed = new MigrationDefinition(
            'fixture.package',
            '001-create-items',
            new MigrationMetadata(null, '1', 'Changed history'),
            [new SqlStatement('CREATE TABLE fixture_items (id INTEGER PRIMARY KEY)')],
        );
        $plan = $this->planner->plan('fixture.package', [$changed]);

        self::assertFalse($plan->executable());
        self::assertStringContainsString('checksum drift', implode(' ', $plan->blockingReasons));
        $this->expectException(MigrationBlocked::class);
        $this->runner->apply($plan);
    }

    public function testMissingAppliedDefinitionBlocksPlan(): void
    {
        $migration = $this->createTableMigration();
        $this->runner->apply($this->planner->plan('fixture.package', [$migration]));

        $plan = $this->planner->plan('fixture.package', []);

        self::assertFalse($plan->executable());
        self::assertStringContainsString('missing from package', implode(' ', $plan->blockingReasons));
    }

    public function testBrokenSchemaVersionChainBlocksPlan(): void
    {
        $first = $this->createTableMigration();
        $second = new MigrationDefinition(
            'fixture.package',
            '002-add-label',
            new MigrationMetadata('unexpected', '2', 'Add label'),
            [new SqlStatement('ALTER TABLE fixture_items ADD COLUMN label VARCHAR(100) NULL')],
        );

        $plan = $this->planner->plan('fixture.package', [$first, $second]);

        self::assertFalse($plan->executable());
        self::assertStringContainsString('expects schema unexpected after 1', implode(' ', $plan->blockingReasons));
    }

    public function testPreflightFailureBlocksMigrationWithoutExecutingDdl(): void
    {
        $check = new class implements MigrationPreflightCheck {
            public function id(): string
            {
                return 'legacy-column';
            }

            public function check(Database $_database, MigrationDefinition $_migration): MigrationPreflightResult
            {
                return MigrationPreflightResult::failed($this->id(), 'Legacy column is not compatible.');
            }
        };
        $migration = new MigrationDefinition(
            'fixture.package',
            '001-create-items',
            new MigrationMetadata(null, '1', 'Create items'),
            [new SqlStatement('CREATE TABLE fixture_items (id INTEGER PRIMARY KEY)')],
            preflightChecks: [$check],
        );

        $plan = $this->planner->plan('fixture.package', [$migration]);

        self::assertFalse($plan->executable());
        self::assertNull($this->table('fixture_items'));
    }

    public function testDestructiveMigrationRequiresApprovalAndBackupConfirmation(): void
    {
        $this->database->execute(new SqlStatement('CREATE TABLE obsolete_data (id INTEGER PRIMARY KEY)'));
        $migration = new MigrationDefinition(
            'fixture.package',
            '001-drop-obsolete',
            new MigrationMetadata(
                null,
                '1',
                'Drop obsolete data',
                MigrationPhase::Contract,
                destructive: true,
                requiresBackup: true,
            ),
            [new SqlStatement('DROP TABLE obsolete_data')],
        );
        $plan = $this->planner->plan('fixture.package', [$migration]);

        try {
            $this->runner->apply($plan);
            self::fail('Destructive migration should require approval.');
        } catch (MigrationBlocked $exception) {
            self::assertStringContainsString('explicit approval', $exception->getMessage());
        }

        try {
            $this->runner->apply($plan, new MigrationExecutionPolicy(allowDestructive: true));
            self::fail('Migration requiring backup should require confirmation.');
        } catch (MigrationBlocked $exception) {
            self::assertStringContainsString('confirmed backup', $exception->getMessage());
        }

        $this->runner->apply($plan, new MigrationExecutionPolicy(true, true));
        self::assertNull($this->table('obsolete_data'));
    }

    public function testPolicyChecksWholePlanBeforeAnyMigrationRuns(): void
    {
        $safe = $this->createTableMigration();
        $destructive = new MigrationDefinition(
            'fixture.package',
            '002-drop-items',
            new MigrationMetadata(
                '1',
                '2',
                'Drop items',
                MigrationPhase::Contract,
                destructive: true,
            ),
            [new SqlStatement('DROP TABLE fixture_items')],
        );
        $plan = $this->planner->plan('fixture.package', [$safe, $destructive]);

        try {
            $this->runner->apply($plan);
            self::fail('Destructive plan should require approval.');
        } catch (MigrationBlocked) {
            self::assertNull($this->table('fixture_items'));
            self::assertSame([], $this->ledger->records('fixture.package'));
        }
    }

    public function testStalePlanCannotApplyMigrationTwice(): void
    {
        $migration = $this->createTableMigration();
        $plan = $this->planner->plan('fixture.package', [$migration]);
        $this->runner->apply($plan);

        $this->expectException(MigrationBlocked::class);
        $this->expectExceptionMessage('stale');
        $this->runner->apply($plan);
    }

    public function testFailedMigrationIsNotWrittenToLedger(): void
    {
        $migration = new MigrationDefinition(
            'fixture.package',
            '001-invalid',
            new MigrationMetadata(null, '1', 'Fail deliberately'),
            [new SqlStatement('ALTER TABLE table_that_does_not_exist ADD COLUMN label TEXT')],
        );

        try {
            $this->runner->apply($this->planner->plan('fixture.package', [$migration]));
            self::fail('Invalid migration should fail.');
        } catch (MigrationFailed $exception) {
            self::assertSame('001-invalid', $exception->migrationId);
        }

        self::assertSame([], $this->ledger->records('fixture.package'));
    }

    public function testReversibilityAndChecksumAreDerivedFromCompleteDefinition(): void
    {
        $migration = new MigrationDefinition(
            'fixture.package',
            '001-create-items',
            new MigrationMetadata(null, '1', 'Create items'),
            [new SqlStatement('CREATE TABLE fixture_items (id INTEGER PRIMARY KEY)')],
            [new SqlStatement('DROP TABLE fixture_items')],
        );

        self::assertTrue($migration->reversible());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $migration->checksum());
    }

    private function createTableMigration(): MigrationDefinition
    {
        return new MigrationDefinition(
            'fixture.package',
            '001-create-items',
            new MigrationMetadata(null, '1', 'Create items'),
            [new SqlStatement('CREATE TABLE fixture_items (id INTEGER PRIMARY KEY)')],
            [new SqlStatement('DROP TABLE fixture_items')],
        );
    }

    /** @return array<string, mixed>|null */
    private function table(string $name): ?array
    {
        return $this->database->fetchOne(new SqlStatement(
            "SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name",
            ['name' => $name],
        ));
    }
}
