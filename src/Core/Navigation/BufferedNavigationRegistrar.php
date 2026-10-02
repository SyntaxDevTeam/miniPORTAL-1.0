<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Navigation;

use SyntaxDevTeam\MiniPortal\Core\Contract\Module\NavigationRegistrar;

final class BufferedNavigationRegistrar implements NavigationRegistrar
{
    /** @var list<NavigationItem> */
    private array $items = [];

    public function __construct(private readonly string $moduleId)
    {
    }

    public function add(string $id, string $label, string $localPath = '/', string $area = 'public'): void
    {
        if (preg_match('/^[a-z][a-z0-9-]{0,31}$/D', $id) !== 1 || trim($label) === ''
            || !in_array($area, ['public', 'admin'], true)
            || !str_starts_with($localPath, '/') || str_contains($localPath, '..')
            || preg_match('~^/[A-Za-z0-9/_-]*$~D', $localPath) !== 1) {
            throw new \InvalidArgumentException('Module navigation item is invalid.');
        }
        foreach ($this->items as $item) {
            if ($item->id === $id) {
                throw new \InvalidArgumentException('Module navigation ID is duplicated.');
            }
        }
        $url = '/modules/' . rawurlencode($this->moduleId) . ($localPath === '/' ? '' : rtrim($localPath, '/'));
        $this->items[] = new NavigationItem($id, $label, $url, $area);
    }

    /** @return list<NavigationItem> */
    public function items(): array
    {
        return $this->items;
    }
}
