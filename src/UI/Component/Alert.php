<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class Alert implements Component
{
    public function __construct(
        public string $message,
        public AlertSeverity $severity = AlertSeverity::Info,
        public ?string $title = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($message) === '' || ($title !== null && trim($title) === '')) {
            throw new \InvalidArgumentException('Alert message and optional title cannot be empty.');
        }
    }

    public static function componentType(): string
    {
        return 'alert';
    }

    public function identity(): ?ComponentIdentity
    {
        return $this->componentIdentity;
    }

    public function children(): array
    {
        return [];
    }
}
