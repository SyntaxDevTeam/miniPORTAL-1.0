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

        self::assertSame(['core.packages', 'core.widgets', 'core.security', 'core.jobs', 'core.audit'], array_keys($migrations));
        self::assertSame('001-create-package-registry', $migrations['core.packages'][0]->id);
        self::assertSame('001-create-widget-placements', $migrations['core.widgets'][0]->id);
        self::assertSame('001-create-identity-store', $migrations['core.security'][0]->id);
        self::assertSame('001-create-job-queue', $migrations['core.jobs'][0]->id);
        self::assertSame('001-create-audit-events', $migrations['core.audit'][0]->id);
    }
}
