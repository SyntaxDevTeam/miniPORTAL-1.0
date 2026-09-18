<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $attributes
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $attributes = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        /** @var array<string, string> $query */
        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $query[$key] = (string) $value;
            }
        }

        return new self($method, $path, $query);
    }

    /** @param array<string, string> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            array_merge($this->attributes, $attributes),
        );
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }
}
