<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Http\Model;

final readonly class HttpRequest
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $method,
        public string $url,
        public array $headers = [],
        public string $body = '',
    ) {
        if (!in_array($method, ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            throw new \InvalidArgumentException('Unsupported HTTP method.');
        }
        $parts = parse_url($url);
        if ($parts === false || !in_array($parts['scheme'] ?? null, ['http', 'https'], true)
            || !isset($parts['host']) || isset($parts['user'], $parts['pass'])
            || isset($parts['user']) || isset($parts['fragment'])) {
            throw new \InvalidArgumentException('An absolute HTTP(S) URL without credentials or fragment is required.');
        }
        foreach ($headers as $name => $value) {
            if (preg_match('/^[A-Za-z0-9!#$%&\'*+.^_`|~-]+$/D', $name) !== 1
                || str_contains($value, "\r") || str_contains($value, "\n")) {
                throw new \InvalidArgumentException('Invalid HTTP header.');
            }
        }
    }
}
