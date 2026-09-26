<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

final readonly class Request
{
    /**
     * @param array<string, string> $query
     * @param array<string, string> $attributes
     * @param array<string, string> $form
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $attributes = [],
        public ?RequestContext $context = null,
        public array $form = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $methodValue = $_SERVER['REQUEST_METHOD'] ?? null;
        $method = is_string($methodValue) && $methodValue !== ''
            ? strtoupper($methodValue)
            : 'GET';

        $uriValue = $_SERVER['REQUEST_URI'] ?? null;
        $uri = is_string($uriValue) && $uriValue !== '' ? $uriValue : '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';

        /** @var array<string, string> $query */
        $query = [];
        foreach ($_GET as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $query[$key] = (string) $value;
            }
        }

        /** @var array<string, string> $form */
        $form = [];
        foreach ($_POST as $key => $value) {
            if (is_string($key) && is_scalar($value)) {
                $form[$key] = (string) $value;
            }
        }

        return new self($method, $path, $query, form: $form);
    }

    /** @param array<string, string> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            array_merge($this->attributes, $attributes),
            $this->context,
            $this->form,
        );
    }

    public function withContext(RequestContext $context): self
    {
        return new self(
            $this->method,
            $this->path,
            $this->query,
            $this->attributes,
            $context,
            $this->form,
        );
    }

    public function formValue(string $name): ?string
    {
        return $this->form[$name] ?? null;
    }

    public function attribute(string $name): ?string
    {
        return $this->attributes[$name] ?? null;
    }
}
