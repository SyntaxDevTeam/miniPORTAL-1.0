<?php

declare(strict_types=1);

$root = dirname(__DIR__);

spl_autoload_register(static function (string $class) use ($root): void {
    $prefixes = [
        'SyntaxDevTeam\\MiniPortal\\Tests\\' => $root . '/tests/',
        'SyntaxDevTeam\\MiniPortal\\' => $root . '/src/',
    ];

    foreach ($prefixes as $prefix => $directory) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }

        $relative = substr($class, strlen($prefix));
        $path = $directory . str_replace('\\', '/', $relative) . '.php';
        if (is_file($path)) {
            require $path;
        }
        return;
    }
});

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleDispatcher;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\ThrowingModule;

$report = (new PackageDiscovery(new ManifestParser()))->discover(__DIR__ . '/Fixtures/packages');
if (count($report->packages) !== 1 || count($report->failures) !== 1) {
    fwrite(STDERR, "Discovery smoke test failed.\n");
    exit(1);
}

$logger = new InMemoryLogger();
$result = (new ModuleDispatcher($logger))->boot(
    'fixture-runtime-error',
    new ThrowingModule(),
    new ModuleContext(CorrelationId::fromString('request-12345678')),
);

if ($result->successful || count($logger->records) !== 1) {
    fwrite(STDERR, "Module isolation smoke test failed.\n");
    exit(1);
}

fwrite(STDOUT, "miniPORTAL core smoke tests passed.\n");
