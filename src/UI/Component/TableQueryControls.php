<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\TableFilter;

final readonly class TableQueryControls implements Component
{
    /**
     * @param list<TableFilter> $filters
     * @param array<string, string> $preservedParameters
     */
    public function __construct(
        public string $action,
        public ?string $searchValue = null,
        public ?string $searchParameter = 'q',
        public string $searchLabel = 'Szukaj',
        private array $filters = [],
        private array $preservedParameters = [],
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (!self::isSafeUrl($action)) {
            throw new \InvalidArgumentException('Table query action must be relative or HTTPS.');
        }
        if ($searchParameter !== null && preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $searchParameter) !== 1) {
            throw new \InvalidArgumentException('Table search parameter must be a safe query parameter name.');
        }
        if ($searchParameter === null && $searchValue !== null) {
            throw new \InvalidArgumentException('Search value requires an enabled search parameter.');
        }
        if ($searchParameter !== null && trim($searchLabel) === '') {
            throw new \InvalidArgumentException('Enabled table search requires a label.');
        }

        $names = [];
        if ($searchParameter !== null) {
            $names[$searchParameter] = true;
        }
        foreach ($filters as $filter) {
            if (isset($names[$filter->name])) {
                throw new \InvalidArgumentException(sprintf('Duplicate table query parameter "%s".', $filter->name));
            }
            $names[$filter->name] = true;
        }
        foreach ($preservedParameters as $name => $_value) {
            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $name) !== 1 || isset($names[$name])) {
                throw new \InvalidArgumentException(sprintf('Invalid or duplicate preserved query parameter "%s".', $name));
            }
            $names[$name] = true;
        }
        if ($searchParameter === null && $filters === [] && $preservedParameters === []) {
            throw new \InvalidArgumentException('Table query controls require search, filters or preserved parameters.');
        }
    }

    public static function componentType(): string
    {
        return 'table_query_controls';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return [];
    }

    /** @return list<TableFilter> */
    public function filters(): array
    {
        return $this->filters;
    }

    /** @return array<string, string> */
    public function preservedParameters(): array
    {
        return $this->preservedParameters;
    }

    private static function isSafeUrl(string $url): bool
    {
        return str_starts_with($url, '/')
            || preg_match('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?(?:\/|$)/D', $url) === 1;
    }
}
