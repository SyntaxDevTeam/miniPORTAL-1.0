<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\DatabasePackageRegistryMigration;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final class DatabasePackageRegistryTest extends TestCase
{
    private Database $database;
    private ManifestParser $parser;
    private DatabasePackageRegistry $registry;

    protected function setUp(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $this->database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $definition = DatabasePackageRegistryMigration::definition();
        $ledger = new DatabaseMigrationLedger($this->database);
        $plan = (new MigrationPlanner($this->database, $ledger))->plan(DatabasePackageRegistryMigration::OWNER_ID, [$definition]);
        (new MigrationRunner($this->database, $ledger))->apply($plan);
        $this->parser = new ManifestParser();
        $this->registry = new DatabasePackageRegistry($this->database, $this->parser);
    }

    public function testActivePointerSurvivesRecreatingRegistryAndDisablePreservesRelease(): void
    {
        $this->registry->add($this->release('1.0.0'));
        $manager = new PackageLifecycleManager($this->registry, new PackageLifecycle());
        foreach ([PackageState::Validated, PackageState::Staged, PackageState::PreflightPassed,
            PackageState::Ready, PackageState::Active] as $state) {
            $manager->transition('fixture-addon', '1.0.0', $state);
        }
        $reopened = new DatabasePackageRegistry($this->database, $this->parser);
        self::assertSame('1.0.0', $reopened->active('fixture-addon')?->manifest->version);
        self::assertSame('/packages/fixture-addon/1.0.0', $reopened->active('fixture-addon')->releasePath);

        (new PackageLifecycleManager($reopened, new PackageLifecycle()))
            ->transition('fixture-addon', '1.0.0', PackageState::Disabled);
        self::assertNull($this->registry->active('fixture-addon'));
        self::assertSame(PackageState::Disabled, $this->registry->find('fixture-addon', '1.0.0')?->state);
    }

    public function testNewReleaseCannotBecomeActiveBeforeReadyAndOldPointerSurvives(): void
    {
        $this->registry->add($this->release('1.0.0'));
        $this->registry->add($this->release('1.1.0'));
        self::assertSame(['1.0.0', '1.1.0'], array_map(
            static fn (PackageRelease $release): string => $release->manifest->version,
            $this->registry->allReleases(),
        ));
        $manager = new PackageLifecycleManager($this->registry, new PackageLifecycle());
        foreach ([PackageState::Validated, PackageState::Staged, PackageState::PreflightPassed,
            PackageState::Ready, PackageState::Active] as $state) {
            $manager->transition('fixture-addon', '1.0.0', $state);
        }
        try {
            $this->registry->setActive('fixture-addon', '1.1.0');
            self::fail('Unready release must not replace active pointer.');
        } catch (\LogicException) {
            self::assertSame('1.0.0', $this->registry->active('fixture-addon')?->manifest->version);
        }
    }

    public function testRequiredPolicyBlocksDisableAndFailureButAllowsDegraded(): void
    {
        $this->registry->add($this->release('1.0.0'));
        $manager = new PackageLifecycleManager(
            $this->registry, new PackageLifecycle(), new RequiredPackagePolicy(['fixture-addon']),
        );
        foreach ([PackageState::Validated, PackageState::Staged, PackageState::PreflightPassed,
            PackageState::Ready, PackageState::Active, PackageState::Degraded] as $state) {
            $manager->transition('fixture-addon', '1.0.0', $state);
        }
        foreach ([PackageState::Disabled, PackageState::Failed] as $forbidden) {
            try {
                $manager->transition('fixture-addon', '1.0.0', $forbidden);
                self::fail('Required package must remain installed.');
            } catch (\DomainException) {
                self::assertSame(PackageState::Degraded, $this->registry->active('fixture-addon')?->state);
            }
        }
    }

    public function testPointerFailureRollsBackReleaseActivation(): void
    {
        $this->registry->add($this->release('1.0.0'));
        $manager = new PackageLifecycleManager($this->registry, new PackageLifecycle());
        foreach ([PackageState::Validated, PackageState::Staged, PackageState::PreflightPassed,
            PackageState::Ready] as $state) {
            $manager->transition('fixture-addon', '1.0.0', $state);
        }
        $activeTable = (new StorageNamespace(DatabasePackageRegistryMigration::OWNER_ID))->table('active')->value;
        $this->database->execute(new SqlStatement(sprintf(
            "CREATE TRIGGER reject_pointer BEFORE INSERT ON %s "
            . "BEGIN SELECT RAISE(ABORT, 'pointer unavailable'); END",
            $activeTable,
        )));
        try {
            $manager->transition('fixture-addon', '1.0.0', PackageState::Active);
            self::fail('Pointer failure must prevent activation.');
        } catch (\Throwable $exception) {
            self::assertSame(PackageState::Ready, $this->registry->find('fixture-addon', '1.0.0')?->state);
            self::assertNull($this->registry->active('fixture-addon'));
        }
    }

    public function testRemoveRejectsActiveReleaseAndDeletesOnlyMetadataAfterDisable(): void
    {
        $this->registry->add($this->release('1.0.0'));
        $manager = new PackageLifecycleManager($this->registry, new PackageLifecycle());
        foreach ([PackageState::Validated, PackageState::Staged, PackageState::PreflightPassed,
            PackageState::Ready, PackageState::Active] as $state) {
            $manager->transition('fixture-addon', '1.0.0', $state);
        }
        try {
            $this->registry->remove('fixture-addon', '1.0.0');
            self::fail('Active release must not be removed.');
        } catch (\LogicException) {
            self::assertNotNull($this->registry->active('fixture-addon'));
        }
        $manager->transition('fixture-addon', '1.0.0', PackageState::Disabled);
        $this->registry->remove('fixture-addon', '1.0.0');
        self::assertNull($this->registry->find('fixture-addon', '1.0.0'));
    }

    private function release(string $version): PackageRelease
    {
        $manifest = $this->parser->parse(json_encode([
            'schema' => 1,
            'id' => 'fixture-addon',
            'name' => 'Fixture addon',
            'version' => $version,
            'type' => 'module',
            'requires' => ['core' => '^1.0'],
            'entrypoint' => 'Fixture\\Addon',
        ], JSON_THROW_ON_ERROR));
        return new PackageRelease($manifest, '/packages/fixture-addon/' . $version, PackageState::Discovered);
    }
}
