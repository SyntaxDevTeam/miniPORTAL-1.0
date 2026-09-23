<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;
use SyntaxDevTeam\MiniPortal\UI\Model\FormMethod;

final readonly class Form implements Component
{
    /** @param list<Component> $fields */
    public function __construct(
        public string $action,
        public FormMethod $method,
        private array $fields,
        public string $submitLabel,
        public ?string $csrfToken = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if ((!str_starts_with($action, '/') && preg_match('/^https:\/\/[A-Za-z0-9.-]+(?::[0-9]+)?(?:\/|$)/D', $action) !== 1)
            || $fields === [] || trim($submitLabel) === '') {
            throw new \InvalidArgumentException('Form requires a safe action, fields and submit label.');
        }
        if ($method === FormMethod::Post && ($csrfToken === null || trim($csrfToken) === '')) {
            throw new \InvalidArgumentException('POST forms require a CSRF token.');
        }
    }

    public static function componentType(): string
    {
        return 'form';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return $this->fields;
    }
}
