<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Widget;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Widget\DatabaseWidgetMigration;
use SyntaxDevTeam\MiniPortal\Core\Widget\DatabaseWidgetPlacementRepository;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetInstance;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class DatabaseWidgetPlacementRepositoryTest extends TestCase
{
    public function testAssignmentPersistsThroughRepositoryRestartAndMissingSlotDoesNotDeleteIt(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $plan = (new MigrationPlanner($database, $ledger))->plan(
            DatabaseWidgetMigration::OWNER_ID, [DatabaseWidgetMigration::definition()],
        );
        (new MigrationRunner($database, $ledger))->apply($plan);
        $repository = new DatabaseWidgetPlacementRepository($database);
        $instance = new WidgetInstance('widget.first', 'home', 'footer.custom', 'fixture.widgets', 'status', 7,
            ['label' => 'Online', 'limit' => 3, 'visible' => true], 'server.view');
        $repository->save($instance);
        $reopened = new DatabaseWidgetPlacementRepository($database);
        self::assertSame([], $reopened->forSlot('home', 'other.slot'));
        self::assertEquals([$instance], $reopened->forSlot('home', 'footer.custom'));
        $reopened->save(new WidgetInstance('widget.first', 'home', 'footer.custom', 'fixture.widgets', 'status', 1));
        self::assertSame(1, $repository->forSlot('home', 'footer.custom')[0]->position);
        $repository->remove('widget.first');
        self::assertSame([], $reopened->forSlot('home', 'footer.custom'));
    }
}
