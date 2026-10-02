<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

use Closure;
use DateTimeImmutable;
use DateTimeZone;
use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContext;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

/** Service-to-service authentication, scope enforcement and rate limiting. */
final readonly class ServiceApiGateway
{
    public function __construct(
        private ServiceTokenStore $tokens,
        private ServiceRateLimiter $limits,
        private Logger $logger,
        private int $requestsPerMinute = 120,
    ) {
        if ($requestsPerMinute < 1 || $requestsPerMinute > 10_000) {
            throw new \InvalidArgumentException('API rate limit is invalid.');
        }
    }

    /** @param Closure(Request): Response $handler */
    public function handle(Request $request, string $scope, Closure $handler): Response
    {
        $authorization = $request->header('authorization');
        if ($authorization === null || preg_match('/^Bearer (mp1_[a-f0-9]{32}_[a-f0-9]{64})$/D', $authorization, $match) !== 1) {
            return $this->error('unauthorized', 401, ['WWW-Authenticate' => 'Bearer']);
        }
        try {
            $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
            $principal = $this->tokens->authenticate($match[1], $now);
            if ($principal === null) {
                return $this->error('unauthorized', 401, ['WWW-Authenticate' => 'Bearer']);
            }
            if (!$principal->hasScope($scope)) {
                return $this->error('insufficient_scope', 403);
            }
            if (!$this->limits->allow($principal->tokenId, $now->getTimestamp(), $this->requestsPerMinute)) {
                return $this->error('rate_limited', 429, ['Retry-After' => (string) (60 - ($now->getTimestamp() % 60))]);
            }
            $context = new RequestContext(
                ($request->context === null ? CorrelationId::generate() : $request->context->correlationId),
                'service:' . $principal->tokenId,
                ($request->context === null ? 'en' : $request->context->locale),
                ($request->context === null ? 'UTC' : $request->context->timezone),
                permissions: $principal->scopes,
            );
            $response = $handler($request->withContext($context));
            if (!str_starts_with(strtolower($response->headers['Content-Type'] ?? ''), 'application/json')) {
                throw new \LogicException('Service API endpoint must return JSON.');
            }
            return $response->withPrivateNoStore();
        } catch (\Throwable $exception) {
            $errorId = (string) CorrelationId::generate();
            $this->logger->error('Service API request failed.', [
                'error_id' => $errorId,
                'correlation_id' => (string) ($request->context === null ? $errorId : $request->context->correlationId),
                'exception' => $exception::class,
            ]);
            return Response::json(['error' => 'service_unavailable', 'error_id' => $errorId], 503);
        }
    }

    /** @param array<string, string> $headers */
    private function error(string $code, int $status, array $headers = []): Response
    {
        $response = Response::json(['error' => $code], $status);
        return new Response($response->body, $status, [...$response->headers, ...$headers]);
    }
}
