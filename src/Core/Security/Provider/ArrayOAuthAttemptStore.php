<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\OAuthAttemptStore;

final class ArrayOAuthAttemptStore implements OAuthAttemptStore
{
    /** @var array<string, array{start: int, count: int}> */
    private array $buckets = [];

    public function record(string $provider, string $stage, int $now, int $windowSeconds): int
    {
        $key = $provider . ':' . $stage;
        $bucket = $this->buckets[$key] ?? null;
        if ($bucket === null || $now < $bucket['start'] || $now - $bucket['start'] >= $windowSeconds) {
            $bucket = ['start' => $now, 'count' => 0];
        }
        $bucket['count']++;
        $this->buckets[$key] = $bucket;
        return $bucket['count'];
    }
}
