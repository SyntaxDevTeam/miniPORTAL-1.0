<?php

declare(strict_types=1);

use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;

require dirname(__DIR__) . '/vendor/autoload.php';

$runtime = Runtime::boot();

Response::html(sprintf(
    '<!doctype html><html lang="en"><meta charset="utf-8"><title>miniPORTAL 1.0</title><body><main><h1>miniPORTAL 1.0</h1><p>Core bootstrap is running.</p><small>Request ID: %s</small></main></body></html>',
    htmlspecialchars((string) $runtime->correlationId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
))->send();
