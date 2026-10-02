<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Migration;

use PDO;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DatabaseIdentityMigration;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\DatabaseMigrationLedger;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationPlanner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationRunner;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;
use SyntaxDevTeam\MiniPortal\Migration\Legacy\IdentityImporter;
use SyntaxDevTeam\MiniPortal\Migration\Legacy\PagesImporter;
use SyntaxDevTeam\MiniPortal\Module\SitePages\SitePageRepository;
use SyntaxDevTeam\MiniPortal\Module\SitePages\SitePagesModule;

final class PagesImporterTest extends TestCase
{
    public function testChecksSnapshotAndImportedAuthorsBeforeOneTimeImport(): void
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            self::markTestSkipped('PDO SQLite driver is unavailable.');
        }
        $database = (new PdoDatabaseFactory())->connect(new PdoConnectionConfig('sqlite::memory:'));
        $ledger = new DatabaseMigrationLedger($database);
        $planner = new MigrationPlanner($database, $ledger);
        $runner = new MigrationRunner($database, $ledger);
        $runner->apply($planner->plan(DatabaseIdentityMigration::OWNER_ID, [DatabaseIdentityMigration::definition()]));
        $runner->apply($planner->plan('site.pages', SitePagesModule::migrations()));
        $users = (new StorageNamespace(DatabaseIdentityMigration::OWNER_ID))->table('users')->value;
        $snapshot = json_encode([
            'schema' => 1, 'timezone' => 'Europe/Warsaw', 'pages' => [[
                'id' => 7, 'title' => 'Kontakt', 'slug' => 'kontakt', 'summary' => 'Napisz do nas',
                'content' => '<p>Witaj</p>', 'content_format' => 'html', 'status' => 'published',
                'author_id' => 3, 'created_at' => '2026-01-02 12:00:00',
                'updated_at' => '2026-01-02 12:05:00',
            ]],
        ], JSON_THROW_ON_ERROR);
        $importer = new PagesImporter($database);
        try {
            $importer->plan($snapshot);
            self::fail('Missing author must block the import.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('author', $exception->getMessage());
        }
        $database->execute(new SqlStatement('INSERT INTO ' . $users
            . ' (id, display_name, status, created_at) VALUES (:id, :name, :status, :created)', [
                'id' => IdentityImporter::newId(3), 'name' => 'Author', 'status' => 'active',
                'created' => '2026-01-02T11:00:00+00:00',
            ]));
        $plan = $importer->plan($snapshot);
        self::assertSame(1, $plan->pages);
        self::assertSame(1, $plan->published);
        try {
            $importer->apply($snapshot, str_repeat('0', 64));
            self::fail('Wrong checksum must be rejected.');
        } catch (\LogicException $exception) {
            self::assertStringContainsString('snapshot', $exception->getMessage());
        }
        $importer->apply($snapshot, $plan->checksum);
        $pages = (new SitePageRepository($database))->listing(false);
        self::assertCount(1, $pages);
        self::assertSame('legacy-7', $pages[0]->id);
        self::assertSame(7, $pages[0]->legacyId);
        self::assertSame('<p>Witaj</p>', $pages[0]->content);
        $this->expectException(\LogicException::class);
        $importer->apply($snapshot, $plan->checksum);
    }
}
