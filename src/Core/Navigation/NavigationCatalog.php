<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Navigation;

use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;

/** Navigation from modules that still own an active release. */
final class NavigationCatalog
{
    /** @var array<string, list<NavigationItem>> */
    private array $items = [];

    public function __construct(private readonly PackageRegistry $registry)
    {
    }

    /** @param list<NavigationItem> $items */
    public function registerModule(string $moduleId, array $items): void
    {
        $this->items[$moduleId] = $items;
    }

    public function unregisterModule(string $moduleId): void
    {
        unset($this->items[$moduleId]);
    }

    /** @return list<PageAction> */
    public function actions(string $area): array
    {
        $actions = [];
        foreach ($this->items as $moduleId => $items) {
            if ($this->registry->active($moduleId) === null) {
                continue;
            }
            foreach ($items as $item) {
                if ($item->area === $area) {
                    $actions[] = new PageAction('nav-' . substr(hash('sha256', $moduleId . ':' . $item->id), 0, 16),
                        $item->label, ActionIntent::Navigate, $item->url);
                }
            }
        }
        return $actions;
    }
}
