<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Migration\CoreMigrationCatalog;

final class CoreMigrationCatalogTest extends TestCase
{
    public function testCatalogContainsOrderedCoreOwnedMigrations(): void
    {
        $migrations = (new CoreMigrationCatalog())->byOwner();

        self::assertSame(['core.jobs', 'core.audit'], array_keys($migrations));
        self::assertSame('001-create-job-queue', $migrations['core.jobs'][0]->id);
        self::assertSame('001-create-audit-events', $migrations['core.audit'][0]->id);
    }
}
