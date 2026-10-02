<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

/** Multiline form field for authored content. */
final readonly class TextAreaField implements Component
{
    public function __construct(
        public string $name,
        public string $label,
        public ?string $value = null,
        public bool $required = false,
        public ?string $help = null,
        public ?string $error = null,
        public int $rows = 8,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1 || trim($label) === '') {
            throw new \InvalidArgumentException('Text area requires a valid name and label.');
        }
        if ($rows < 2 || $rows > 40 || ($help !== null && trim($help) === '')
            || ($error !== null && trim($error) === '')) {
            throw new \InvalidArgumentException('Text area configuration is invalid.');
        }
    }

    public static function componentType(): string { return 'text_area_field'; }
    public function identity(): ?ComponentIdentity { return $this->componentIdentity; }
    public function children(): array { return []; }
}
