<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Http\Contract;

use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpRequest;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpResponse;

interface HttpClient
{
    public function send(HttpRequest $request): HttpResponse;
}
