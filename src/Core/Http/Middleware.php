<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

interface Middleware
{
    public function process(Request $request, RequestHandler $next): Response;
}
