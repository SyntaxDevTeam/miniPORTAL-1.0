<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final readonly class TableFilter
{
    /** @param array<string, string> $options */
    public function __construct(
        public string $name,
        public string $label,
        public array $options,
        public ?string $selected = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $name) !== 1 || trim($label) === '' || $options === []) {
            throw new \InvalidArgumentException('Table filter requires a valid name, label and options.');
        }
        foreach ($options as $optionLabel) {
            if (trim($optionLabel) === '') {
                throw new \InvalidArgumentException('Table filter option labels cannot be empty.');
            }
        }
        if ($selected !== null && !array_key_exists($selected, $options)) {
            throw new \InvalidArgumentException('Selected table filter value must exist in options.');
        }
    }
}
