<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\OAuthAttemptStore;

final class NativeOAuthAttemptStore implements OAuthAttemptStore
{
    private const KEY = 'miniportal_oauth_attempts';

    public function record(string $provider, string $stage, int $now, int $windowSeconds): int
    {
        $key = $provider . ':' . $stage;
        $buckets = $_SESSION[self::KEY] ?? [];
        if (!is_array($buckets)) {
            $buckets = [];
        }
        $bucket = $buckets[$key] ?? null;
        if (!is_array($bucket) || !is_int($bucket['start'] ?? null) || !is_int($bucket['count'] ?? null)
            || $now < $bucket['start'] || $now - $bucket['start'] >= $windowSeconds) {
            $start = $now;
            $count = 0;
        } else {
            $start = $bucket['start'];
            $count = $bucket['count'];
        }
        $bucket = ['start' => $start, 'count' => $count + 1];
        $buckets[$key] = $bucket;
        $_SESSION[self::KEY] = $buckets;
        return $bucket['count'];
    }
}
