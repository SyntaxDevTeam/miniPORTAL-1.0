<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

final readonly class Response
{
    /** @param array<string, string> $headers */
    public function __construct(
        public string $body,
        public int $status = 200,
        public array $headers = ['Content-Type' => 'text/html; charset=UTF-8'],
    ) {
        if ($status < 100 || $status > 599) {
            throw new \InvalidArgumentException('HTTP status must be between 100 and 599.');
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status);
    }

    public static function text(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    public static function redirect(string $location, int $status = 303): self
    {
        if (!str_starts_with($location, '/') || str_starts_with($location, '//')) {
            throw new \InvalidArgumentException('Redirect location must be a local absolute path.');
        }
        return new self('', $status, ['Location' => $location, 'Cache-Control' => 'private, no-store']);
    }

    public function withPrivateNoStore(): self
    {
        return new self($this->body, $this->status, [...$this->headers, 'Cache-Control' => 'private, no-store']);
    }

    public function send(): never
    {
        http_response_code($this->status);
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        echo $this->body;
        exit;
    }
}
