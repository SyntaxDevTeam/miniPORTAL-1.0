<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Plasma;

use SyntaxDevTeam\MiniPortal\UI\Model\ActionIntent;
use SyntaxDevTeam\MiniPortal\UI\Model\PageAction;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final readonly class PlasmaPageRenderer
{
    /** @var list<string> */
    private const SUPPORTED_LAYOUTS = ['public', 'application', 'dashboard'];

    public function __construct(private RendererRegistry $renderers, private string $assetBaseUrl)
    {
    }

    public function render(PageDefinition $page, string $language = 'pl'): string
    {
        if (!in_array($page->layoutRole, self::SUPPORTED_LAYOUTS, true)) {
            throw new \InvalidArgumentException(sprintf('Plasma does not support the "%s" layout role.', $page->layoutRole));
        }
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', $language) !== 1) {
            throw new \InvalidArgumentException('Page language must be a valid language tag.');
        }

        $isPublic = $page->layoutRole === 'public';
        $assetBase = rtrim($this->assetBaseUrl, '/');

        return '<!doctype html><html lang="' . Html::escape($language) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#071a2e"><meta name="color-scheme" content="dark">'
            . '<title>' . Html::escape($page->title) . ' — miniPORTAL</title>'
            . '<link rel="stylesheet" href="' . Html::escape($assetBase) . '/theme.css">'
            . '<script defer src="' . Html::escape($assetBase) . '/theme.js"></script>'
            . '</head><body><div class="mp-plasma app-shell ' . ($isPublic ? 'public-shell' : 'admin-shell')
            . ' menu-expanded" data-menu-state="expanded">'
            . $this->sidebar($isPublic, $page)
            . '<button class="menu-backdrop" type="button" data-menu-backdrop aria-label="Zamknij menu"></button>'
            . $this->topbar($page, $isPublic)
            . ($isPublic ? $this->publicMain($page) : $this->adminMain($page))
            . $this->footer($page, $isPublic)
            . $this->searchOverlay()
            . '</div></body></html>';
    }

    private function sidebar(bool $isPublic, PageDefinition $page): string
    {
        $links = $isPublic
            ? [['/', 'Start', 'home'], ['/admin', 'Panel', 'dashboard']]
            : [['/admin', 'Przegląd', 'dashboard'], ['/admin/users', 'Użytkownicy', 'dashboard'], ['/', 'Strona publiczna', 'home']];
        $items = '';
        foreach ($links as [$url, $label, $icon]) {
            $active = ($isPublic && $url === '/') || (!$isPublic && (($url === '/admin' && $page->id !== 'admin-users') || ($url === '/admin/users' && $page->id === 'admin-users')));
            $items .= '<a class="nav-item' . ($active ? ' active' : '') . '" href="' . Html::escape($url) . '"'
                . ($active ? ' aria-current="page"' : '') . '><span class="nav-icon" aria-hidden="true">'
                . $this->icon($icon) . '</span><span class="nav-label">' . Html::escape($label) . '</span></a>';
        }

        return '<aside class="sidebar frosted" id="plasma-navigation" aria-label="Nawigacja '
            . ($isPublic ? 'główna' : 'panelu administratora') . '"><div class="brand-row">'
            . '<a class="brand-mark" href="/" aria-label="miniPORTAL — strona główna">' . $this->icon('brand') . '</a>'
            . '<div class="brand-copy"><div class="brand-title">miniPORTAL</div><div class="brand-subtitle">'
            . ($isPublic ? 'Nowoczesny portal' : 'Panel administratora') . '</div></div></div>'
            . '<nav class="sidebar-nav" aria-label="Sekcje">' . $items . '</nav>'
            . '<div class="sidebar-bottom"><button class="collapse-wide" type="button" data-menu-toggle '
            . 'aria-controls="plasma-navigation" aria-expanded="true"><span class="nav-icon" aria-hidden="true">'
            . $this->icon('collapse') . '</span><span class="nav-label" data-toggle-label>Zwiń menu</span></button></div></aside>';
    }

    private function topbar(PageDefinition $page, bool $isPublic): string
    {
        return '<header class="topbar frosted"><button class="menu-toggle" type="button" data-menu-toggle '
            . 'aria-label="Pokaż lub ukryj menu" aria-expanded="true">' . $this->icon('menu') . '</button>'
            . '<button class="search-trigger" type="button" data-search-trigger aria-haspopup="dialog">'
            . $this->icon('search') . '<span class="search-label">Szukaj w miniPORTAL…</span><span class="shortcut">Ctrl K</span></button>'
            . '<div class="top-actions"><button class="icon-btn" type="button" aria-label="Ogranicz efekty świetlne" '
            . 'aria-pressed="false" data-calm-toggle>' . $this->icon('sun') . '</button><span class="topbar-context">'
            . Html::escape($isPublic ? 'Public' : $page->title) . '</span></div></header>';
    }

    private function publicMain(PageDefinition $page): string
    {
        return '<main class="public-main"><section class="hero"><div class="hero-grid"><div>'
            . $this->breadcrumbs($page)
            . '<div class="eyebrow-row"><span class="pill">miniPORTAL 1.0</span><span class="pill green">Core online</span></div>'
            . '<h1>' . Html::escape($page->title) . '</h1><div class="hero-copy">'
            . $this->renderers->renderMany($page->region(PageRegion::PAGE_HEADER)) . '</div>'
            . '<div class="hero-actions">' . $this->actions($page->actions) . '</div></div>'
            . '<div class="terminal" aria-label="Stan platformy"><div class="traffic" aria-hidden="true"><i></i><i></i><i></i></div>'
            . '<span class="tag">core/status</span><pre><span class="comment">$ miniportal doctor</span>\n'
            . '<span class="ok">✓ Core gotowy</span>\n<span class="ok">✓ UI contracts aktywne</span>\n'
            . '<span class="ok">✓ Plasma Theme załadowany</span></pre></div></div></section>'
            . '<section class="content-zone"><div class="page-frame"><div class="content-grid">'
            . '<section class="content-region" aria-label="Treść">'
            . $this->renderers->renderMany($page->region(PageRegion::CONTENT)) . '</section>'
            . $this->region($page, PageRegion::ASIDE, 'aside-region') . '</div></div></section></main>';
    }

    private function adminMain(PageDefinition $page): string
    {
        return '<main class="admin-main"><div class="admin-wrap">' . $this->breadcrumbs($page)
            . '<header class="admin-header"><div><span class="pill">miniPORTAL 1.0</span><h1>'
            . Html::escape($page->title) . '</h1><div class="admin-lead">'
            . $this->renderers->renderMany($page->region(PageRegion::PAGE_HEADER)) . '</div></div>'
            . '<div class="admin-header-actions">' . $this->actions($page->actions)
            . '<span class="system-ok"><i class="dot"></i> System działa</span></div></header>'
            . '<div class="content-grid admin-content-grid"><section class="content-region" aria-label="Treść panelu">'
            . $this->renderers->renderMany($page->region(PageRegion::CONTENT)) . '</section>'
            . $this->region($page, PageRegion::ASIDE, 'aside-region') . '</div></div></main>';
    }

    private function footer(PageDefinition $page, bool $isPublic): string
    {
        $content = $this->renderers->renderMany($page->region(PageRegion::FOOTER));
        return '<footer class="footer frosted"><div class="footer-brand">' . $this->icon('brand')
            . '<div><strong>miniPORTAL</strong><div class="brand-subtitle">Plasma Theme</div></div></div>'
            . '<div class="footer-links">' . ($content === '' ? '<span>Modularnie · bezpiecznie · nowocześnie</span>' : $content) . '</div>'
            . '<div class="footer-meta">' . ($isPublic ? 'Public UI' : 'Administration UI') . '<br>UI API 1.0</div></footer>';
    }

    private function searchOverlay(): string
    {
        return '<div class="search-overlay" hidden role="dialog" aria-modal="true" aria-labelledby="plasma-search-title">'
            . '<div class="search-modal frosted"><div class="search-modal-head"><strong id="plasma-search-title">Szukaj w miniPORTAL</strong>'
            . '<button type="button" data-search-close aria-label="Zamknij wyszukiwanie">×</button></div>'
            . '<input class="search-input" type="search" placeholder="Wpisz nazwę sekcji…" aria-describedby="plasma-search-hint">'
            . '<div class="search-suggestions"><a href="/"><strong>Strona główna</strong><small>Publiczny widok portalu</small></a>'
            . '<a href="/admin"><strong>Panel administracyjny</strong><small>Zarządzanie miniPORTAL</small></a></div>'
            . '<p class="search-hint" id="plasma-search-hint">Esc zamyka wyszukiwanie. Wyniki modułów zostaną podłączone przez UI API.</p>'
            . '</div></div>';
    }

    private function breadcrumbs(PageDefinition $page): string
    {
        if ($page->breadcrumbs === []) {
            return '';
        }
        $items = [];
        foreach ($page->breadcrumbs as $breadcrumb) {
            $label = Html::escape($breadcrumb->label);
            $items[] = $breadcrumb->url === null
                ? '<span aria-current="page">' . $label . '</span>'
                : '<a href="' . Html::escape($breadcrumb->url) . '">' . $label . '</a>';
        }
        return '<nav class="breadcrumbs" aria-label="Okruszki">' . implode('<span aria-hidden="true">/</span>', $items) . '</nav>';
    }

    /** @param list<PageAction> $actions */
    private function actions(array $actions): string
    {
        $html = '';
        foreach ($actions as $action) {
            $class = in_array($action->intent, [ActionIntent::Submit, ActionIntent::StartJob], true)
                ? 'primary-button' : 'secondary-button';
            $tag = $action->url === null ? 'button' : 'a';
            $attributes = $action->url === null ? ' type="button"' : ' href="' . Html::escape($action->url) . '"';
            $confirmation = $action->confirmationRequired ? ' data-confirmation-required="true"' : '';
            $html .= '<' . $tag . ' class="' . $class . '"' . $attributes . $confirmation . '>'
                . Html::escape($action->label) . '</' . $tag . '>';
        }
        return $html;
    }

    private function region(PageDefinition $page, string $name, string $class): string
    {
        $components = $page->region($name);
        return $components === [] ? '' : '<aside class="' . $class . '">' . $this->renderers->renderMany($components) . '</aside>';
    }

    private function icon(string $name): string
    {
        $path = match ($name) {
            'brand' => '<path d="m12 2 8 4.5v11L12 22l-8-4.5v-11L12 2Z"/><path d="m4 6.5 8 4.5 8-4.5M12 11v11"/>',
            'home' => '<path d="m3 11 9-8 9 8"/><path d="M5 10v10h14V10M9 20v-6h6v6"/>',
            'dashboard' => '<rect x="4" y="3" width="16" height="6" rx="1"/><rect x="4" y="10" width="16" height="5" rx="1"/><rect x="4" y="16" width="16" height="5" rx="1"/>',
            'collapse' => '<path d="m13 6-6 6 6 6M19 6l-6 6 6 6"/>',
            'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
            'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
            'sun' => '<circle cx="12" cy="12" r="4"/><path d="M12 2v3M12 19v3M4.9 4.9 7 7M17 17l2.1 2.1M2 12h3M19 12h3M4.9 19.1 7 17M17 7l2.1-2.1"/>',
            default => throw new \LogicException('Unknown Plasma icon.'),
        };
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true">' . $path . '</svg>';
    }
}
