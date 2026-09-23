<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\InputType;

final readonly class TextField implements Component
{
    public function __construct(
        public string $name,
        public string $label,
        public InputType $type = InputType::Text,
        public ?string $value = null,
        public bool $required = false,
        public ?string $help = null,
        public ?string $error = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('Text field requires a valid name and label.');
        }
        if (($help !== null && trim($help) === '') || ($error !== null && trim($error) === '')) {
            throw new \InvalidArgumentException('Text field help and error cannot be empty when present.');
        }
        if ($type === InputType::Password && $value !== null) {
            throw new \InvalidArgumentException('Password fields cannot contain a prefilled value.');
        }
    }

    public static function componentType(): string { return 'text_field'; }
    public function identity(): ?ComponentIdentity { return $this->componentIdentity; }
    public function children(): array { return []; }
}
