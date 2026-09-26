<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\OAuthAttemptStore;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;

final readonly class OAuthAttemptLimiter
{
    public function __construct(
        private OAuthAttemptStore $store,
        private Clock $clock,
        private int $startLimit = 10,
        private int $callbackLimit = 20,
        private int $windowSeconds = 600,
    ) {
        if ($startLimit < 1 || $callbackLimit < 1 || $windowSeconds < 60) {
            throw new \InvalidArgumentException('OAuth attempt limits are invalid.');
        }
    }

    public function recordStart(string $provider): void
    {
        $this->record($provider, 'start', $this->startLimit);
    }

    public function recordCallback(string $provider): void
    {
        $this->record($provider, 'callback', $this->callbackLimit);
    }

    private function record(string $provider, string $stage, int $limit): void
    {
        $attempts = $this->store->record(
            $provider,
            $stage,
            $this->clock->now()->getTimestamp(),
            $this->windowSeconds,
        );
        if ($attempts > $limit) {
            throw new OAuthRateLimitExceeded('OAuth attempt rate limit exceeded.');
        }
    }
}
