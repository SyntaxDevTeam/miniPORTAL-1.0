<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class CheckboxField implements Component
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $checked = false,
        public bool $required = false,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('Checkbox field requires a valid name and label.');
        }
    }

    public static function componentType(): string { return 'checkbox_field'; }
    public function identity(): ?ComponentIdentity { return $this->componentIdentity; }
    public function children(): array { return []; }
}
