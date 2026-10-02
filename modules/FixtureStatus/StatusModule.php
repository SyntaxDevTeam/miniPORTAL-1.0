<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Module\FixtureStatus;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleMigrationProvider;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationDefinition;
use SyntaxDevTeam\MiniPortal\Library\Storage\Migration\MigrationMetadata;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

/** Small lifecycle fixture; no domain behavior or data. */
final class StatusModule implements Module, ModuleMigrationProvider
{
    public static function migrations(): array
    {
        $table = (new StorageNamespace('fixture.status'))->table('probe')->value;
        return [new MigrationDefinition('fixture.status', '001-probe',
            new MigrationMetadata(null, '1', 'Fixture migration probe'),
            [new SqlStatement('CREATE TABLE ' . $table . ' (id INTEGER PRIMARY KEY)')],
            [new SqlStatement('DROP TABLE ' . $table)])];
    }

    public function register(ModuleRegistration $registration): void
    {
        $registration->routes->get('/status', 'status', static fn (Request $_): Response => Response::text('fixture ready'));
    }

    public function boot(ModuleContext $context): void
    {
    }
}
