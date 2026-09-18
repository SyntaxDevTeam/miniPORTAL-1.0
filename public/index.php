<?php

declare(strict_types=1);

use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;

require dirname(__DIR__) . '/vendor/autoload.php';

$runtime = Runtime::boot();
$services = (new CompositionRoot())->build($runtime);
$router = $services->get(Router::class);

if (!$router instanceof Router) {
    throw new LogicException('Router service has invalid type.');
}

$router->add(
    'GET',
    '/',
    'core.home',
    static fn (Request $_): Response => Response::html(sprintf(
        '<!doctype html><html lang="en"><meta charset="utf-8"><title>miniPORTAL 1.0</title><body><main><h1>miniPORTAL 1.0</h1><p>Core bootstrap is running.</p><small>Request ID: %s</small></main></body></html>',
        htmlspecialchars((string) $runtime->correlationId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
    )),
);

$router->handle(Request::fromGlobals())->send();
