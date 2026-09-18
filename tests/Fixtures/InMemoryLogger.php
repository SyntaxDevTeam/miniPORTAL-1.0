<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Fixtures;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;

final class InMemoryLogger implements Logger
{
    /** @var list<array{message: string, context: array<string, scalar|null>}> */
    public array $records = [];

    public function error(string $message, array $context = []): void
    {
        $this->records[] = ['message' => $message, 'context' => $context];
    }
}
