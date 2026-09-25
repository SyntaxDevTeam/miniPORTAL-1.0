<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class Pagination implements Component
{
    public function __construct(
        public int $currentPage,
        public int $totalPages,
        public ?string $previousUrl = null,
        public ?string $nextUrl = null,
        public string $label = 'Paginacja',
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if ($totalPages < 1 || $currentPage < 1 || $currentPage > $totalPages || trim($label) === '') {
            throw new \InvalidArgumentException('Pagination requires a valid current page, total page count and label.');
        }
        if (($previousUrl !== null && !self::isSafeUrl($previousUrl))
            || ($nextUrl !== null && !self::isSafeUrl($nextUrl))) {
            throw new \InvalidArgumentException('Pagination URLs must be relative or HTTPS.');
        }
        if (($currentPage === 1 && $previousUrl !== null)
            || ($currentPage > 1 && $previousUrl === null)
            || ($currentPage === $totalPages && $nextUrl !== null)
            || ($currentPage < $totalPages && $nextUrl === null)) {
            throw new \InvalidArgumentException('Pagination links must match the current page boundaries.');
        }
    }

    public static function componentType(): string
    {
        return 'pagination';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return [];
    }

    private static function isSafeUrl(string $url): bool
    {
        return str_starts_with($url, '/')
            || preg_match('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?(?:\/|$)/D', $url) === 1;
    }
}
