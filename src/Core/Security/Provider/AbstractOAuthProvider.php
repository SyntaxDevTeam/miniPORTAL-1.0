<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Library\Http\Contract\HttpClient;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpRequest;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpResponse;

abstract class AbstractOAuthProvider
{
    public function __construct(
        protected readonly HttpClient $http,
        protected readonly string $clientId,
        protected readonly string $clientSecret,
        protected readonly string $callbackUrl,
    ) {
    }

    /**
     * @param array<string, string> $form
     * @return array<string|int, mixed>
     */
    protected function postForm(string $url, array $form): array
    {
        return $this->decode($this->http->send(new HttpRequest('POST', $url, [
            'Accept' => 'application/json',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'User-Agent' => 'miniPORTAL/1.0',
        ], http_build_query($form, '', '&', PHP_QUERY_RFC3986))));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string|int, mixed>
     */
    protected function getJson(string $url, array $headers = []): array
    {
        return $this->decode($this->http->send(new HttpRequest('GET', $url, [
            'Accept' => 'application/json',
            'User-Agent' => 'miniPORTAL/1.0',
            ...$headers,
        ])));
    }

    /** @return array<string|int, mixed> */
    protected function decode(HttpResponse $response): array
    {
        if ($response->status < 200 || $response->status >= 300) {
            throw new \RuntimeException('Identity provider rejected the request.');
        }
        try {
            $data = json_decode($response->body, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException('Identity provider returned invalid JSON.', previous: $exception);
        }
        if (!is_array($data)) {
            throw new \RuntimeException('Identity provider returned an invalid payload.');
        }
        return $data;
    }

    /** @param array<string, scalar> $parameters */
    protected function url(string $base, array $parameters): string
    {
        return $base . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }
}
