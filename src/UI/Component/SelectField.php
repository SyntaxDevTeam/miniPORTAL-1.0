<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class SelectField implements Component
{
    /** @param array<string, string> $options */
    public function __construct(
        public string $name,
        public string $label,
        public array $options,
        public ?string $selected = null,
        public bool $required = false,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1 || trim($label) === '' || $options === []) {
            throw new \InvalidArgumentException('Select field requires a valid name, label and options.');
        }
        foreach ($options as $value => $option) {
            if ($value === '' || trim($option) === '') {
                throw new \InvalidArgumentException('Select options require non-empty values and labels.');
            }
        }
        if ($selected !== null && !array_key_exists($selected, $options)) {
            throw new \InvalidArgumentException('Selected value must exist in select options.');
        }
    }

    public static function componentType(): string { return 'select_field'; }
    public function identity(): ?ComponentIdentity { return $this->componentIdentity; }
    public function children(): array { return []; }
}
