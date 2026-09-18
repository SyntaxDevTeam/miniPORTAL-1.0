<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

interface RequestHandler
{
    public function handle(Request $request): Response;
}
