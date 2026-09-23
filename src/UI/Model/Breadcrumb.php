<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class Breadcrumb
{
    public function __construct(
        public string $label,
        public ?string $url = null,
    ) {
        if (trim($label) === '') {
            throw new \InvalidArgumentException('Breadcrumb label cannot be empty.');
        }
        if ($url !== null && !self::safeUrl($url)) {
            throw new \InvalidArgumentException('Breadcrumb URL must be relative or HTTPS.');
        }
    }

    private static function safeUrl(string $url): bool
    {
        return str_starts_with($url, '/')
            || preg_match('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?(?:\/|$)/D', $url) === 1;
    }
}
