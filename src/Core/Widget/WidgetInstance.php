<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Widget;

final readonly class WidgetInstance
{
    /** @param array<string, scalar|null> $configuration */
    public function __construct(
        public string $id,
        public string $pageId,
        public string $slot,
        public string $moduleId,
        public string $type,
        public int $position,
        public array $configuration = [],
        public ?string $requiredPermission = null,
    ) {
        foreach ([$id, $pageId, $slot, $moduleId, $type] as $value) {
            if (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $value) !== 1) {
                throw new \InvalidArgumentException('Widget identifier is invalid.');
            }
        }
        if ($position < 0 || ($requiredPermission !== null && trim($requiredPermission) === '')) {
            throw new \InvalidArgumentException('Widget placement is invalid.');
        }
        foreach ($configuration as $key => $value) {
            if (trim($key) === '') {
                throw new \InvalidArgumentException('Widget configuration must be a flat object of scalar values.');
            }
        }
    }
}
