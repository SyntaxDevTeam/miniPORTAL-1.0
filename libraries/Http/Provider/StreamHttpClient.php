<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Http\Provider;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Http\Contract\HttpClient;
use SyntaxDevTeam\MiniPortal\Library\Http\Exception\HttpTransportFailed;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpPolicy;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpRequest;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpResponse;

final class StreamHttpClient implements HttpClient
{
    /** @var Closure(HttpRequest, float): HttpResponse */
    private Closure $transport;

    /** @var (Closure(string, int, ?int, bool): void)|null */
    private ?Closure $diagnostic;

    /**
     * @param (Closure(HttpRequest, float): HttpResponse)|null $transport
     * @param (Closure(string, int, ?int, bool): void)|null $diagnostic
     */
    public function __construct(
        private readonly HttpPolicy $policy = new HttpPolicy(),
        ?Closure $transport = null,
        ?Closure $diagnostic = null,
    ) {
        $this->transport = $transport ?? self::sendWithStreams(...);
        $this->diagnostic = $diagnostic;
    }

    public function send(HttpRequest $request): HttpResponse
    {
        $retryableMethod = in_array($request->method, ['GET', 'HEAD', 'PUT', 'DELETE'], true);
        for ($attempt = 1; $attempt <= $this->policy->maxAttempts; $attempt++) {
            try {
                $response = ($this->transport)($request, $this->policy->timeoutSeconds);
                $retryableStatus = in_array($response->status, [429, 502, 503, 504], true);
                $this->diagnostic && ($this->diagnostic)($request->method, $attempt, $response->status, false);
                if (!$retryableMethod || !$retryableStatus || $attempt === $this->policy->maxAttempts) {
                    return $response;
                }
            } catch (HttpTransportFailed $exception) {
                $this->diagnostic && ($this->diagnostic)($request->method, $attempt, null, true);
                if (!$retryableMethod || $attempt === $this->policy->maxAttempts) {
                    throw $exception;
                }
            }
        }

        throw new \LogicException('Unreachable HTTP retry state.');
    }

    private static function sendWithStreams(HttpRequest $request, float $timeout): HttpResponse
    {
        $headers = [];
        foreach ($request->headers as $name => $value) {
            $headers[] = $name . ': ' . $value;
        }
        $context = stream_context_create(['http' => [
            'method' => $request->method,
            'header' => implode("\r\n", $headers),
            'content' => $request->body,
            'timeout' => $timeout,
            'ignore_errors' => true,
            'follow_location' => 0,
        ]]);
        $body = @file_get_contents($request->url, false, $context);
        if ($body === false) {
            throw new HttpTransportFailed('HTTP transport failed.');
        }
        $rawHeaders = $http_response_header;
        if (!isset($rawHeaders[0]) || preg_match('/^HTTP\/\S+ ([1-5][0-9]{2})\b/', $rawHeaders[0], $match) !== 1) {
            throw new HttpTransportFailed('HTTP response status is unavailable.');
        }
        $responseHeaders = [];
        foreach (array_slice($rawHeaders, 1) as $line) {
            $separator = strpos($line, ':');
            if ($separator !== false) {
                $responseHeaders[strtolower(substr($line, 0, $separator))] = trim(substr($line, $separator + 1));
            }
        }

        return new HttpResponse((int) $match[1], $responseHeaders, $body);
    }
}
