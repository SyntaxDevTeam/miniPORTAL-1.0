<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Logging;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;

final class ErrorLogLogger implements Logger
{
    public function error(string $message, array $context = []): void
    {
        $suffix = $context === []
            ? ''
            : ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        error_log('[miniPORTAL] ' . $message . ($suffix ?: ''));
    }
}
