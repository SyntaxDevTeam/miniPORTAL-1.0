<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\Breadcrumb;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\Validation\ComponentTreeValidator;

final readonly class PageDefinition
{
    /**
     * @param array<string, list<Component>> $regions
     * @param list<Breadcrumb> $breadcrumbs
     * @param list<PageAction> $actions
     */
    public function __construct(
        public string $id,
        public string $title,
        public string $layoutRole,
        public array $regions,
        public array $breadcrumbs = [],
        public array $actions = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]{0,127}$/D', $id) !== 1 || trim($title) === '') {
            throw new \InvalidArgumentException('Page definition requires a valid ID and title.');
        }
        if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $layoutRole) !== 1) {
            throw new \InvalidArgumentException('Page layout role must be semantic.');
        }
        foreach ($regions as $name => $_components) {
            PageRegion::validate($name);
        }
        $actionIds = [];
        foreach ($actions as $action) {
            if (isset($actionIds[$action->id])) {
                throw new \InvalidArgumentException(sprintf('Duplicate page action ID: %s.', $action->id));
            }
            $actionIds[$action->id] = true;
        }
        (new ComponentTreeValidator())->validate($regions);
    }

    /** @return list<Component> */
    public function region(string $name): array
    {
        PageRegion::validate($name);
        return $this->regions[$name] ?? [];
    }
}
