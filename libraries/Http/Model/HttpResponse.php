<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Http\Model;

final readonly class HttpResponse
{
    /** @param array<string, string> $headers */
    public function __construct(
        public int $status,
        public array $headers,
        public string $body,
    ) {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('Invalid HTTP response status.');
        }
    }
}
