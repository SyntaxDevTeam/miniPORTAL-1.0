<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Component;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

final readonly class PermissionDeniedState implements Component
{
    public function __construct(
        public string $title = 'Brak dostępu',
        public ?string $description = null,
        public ?string $requiredPermission = null,
        private ?ComponentIdentity $componentIdentity = null,
    ) {
        if (trim($title) === '' || ($description !== null && trim($description) === '')) {
            throw new \InvalidArgumentException('Permission denied state requires a title and a non-empty optional description.');
        }
        if ($requiredPermission !== null
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,127}$/D', $requiredPermission) !== 1) {
            throw new \InvalidArgumentException('Required permission must be a safe permission identifier.');
        }
    }

    public static function componentType(): string
    {
        return 'permission_denied_state';
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
