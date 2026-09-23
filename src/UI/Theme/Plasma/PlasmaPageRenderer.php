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

    public function __construct(
        private RendererRegistry $renderers,
        private string $assetBaseUrl,
    ) {
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
        $shell = $isPublic ? 'public-shell' : 'admin-shell';
        $sectionLabel = $isPublic ? 'Strona główna' : 'Panel administracyjny';

        return '<!doctype html><html lang="' . Html::escape($language) . '"><head>'
            . '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="color-scheme" content="dark"><title>' . Html::escape($page->title) . ' — miniPORTAL</title>'
            . '<link rel="stylesheet" href="' . Html::escape(rtrim($this->assetBaseUrl, '/')) . '/theme.css">'
            . '</head><body><div class="mp-plasma ' . $shell . '">'
            . $this->sidebar($sectionLabel, $isPublic)
            . $this->topbar($page)
            . '<main class="plasma-main"><div class="page-frame">'
            . $this->breadcrumbs($page)
            . '<header class="page-heading"><div><span class="eyebrow">miniPORTAL 1.0</span><h1>' . Html::escape($page->title) . '</h1></div>'
            . '<div class="page-actions">' . $this->actions($page->actions) . '</div></header>'
            . $this->region($page, PageRegion::PAGE_HEADER, 'page-lead')
            . '<div class="content-grid"><section class="content-region" aria-label="Treść">'
            . $this->renderers->renderMany($page->region(PageRegion::CONTENT))
            . '</section>' . $this->region($page, PageRegion::ASIDE, 'aside-region') . '</div>'
            . '</div></main>'
            . $this->region($page, PageRegion::FOOTER, 'plasma-footer')
            . '</div></body></html>';
    }

    private function sidebar(string $sectionLabel, bool $isPublic): string
    {
        $links = $isPublic
            ? [['/', 'Start'], ['/admin', 'Panel'], ['#features', 'Możliwości']]
            : [['/admin', 'Przegląd'], ['/', 'Strona publiczna'], ['#system', 'System']];
        $items = '';
        foreach ($links as [$url, $label]) {
            $items .= '<a class="nav-item" href="' . Html::escape($url) . '"><span class="nav-dot" aria-hidden="true"></span><span>'
                . Html::escape($label) . '</span></a>';
        }
        return '<aside class="plasma-sidebar" aria-label="Nawigacja główna"><a class="brand" href="/">'
            . '<span class="brand-mark" aria-hidden="true">mP</span><span><strong>miniPORTAL</strong><small>'
            . Html::escape($sectionLabel) . '</small></span></a><nav>' . $items . '</nav>'
            . '<div class="sidebar-status"><span></span> Core działa poprawnie</div></aside>';
    }

    private function topbar(PageDefinition $page): string
    {
        return '<header class="plasma-topbar"><span class="mobile-brand">miniPORTAL</span>'
            . '<button class="search-trigger" type="button" aria-label="Otwórz wyszukiwanie"><span aria-hidden="true">⌕</span> Szukaj w miniPORTAL <kbd>Ctrl K</kbd></button>'
            . '<span class="topbar-context">' . Html::escape($page->layoutRole) . '</span></header>';
    }

    private function breadcrumbs(PageDefinition $page): string
    {
        if ($page->breadcrumbs === []) {
            return '';
        }
        $items = [];
        foreach ($page->breadcrumbs as $breadcrumb) {
            $label = Html::escape($breadcrumb->label);
            $items[] = $breadcrumb->url === null ? '<span aria-current="page">' . $label . '</span>' : '<a href="' . Html::escape($breadcrumb->url) . '">' . $label . '</a>';
        }
        return '<nav class="breadcrumbs" aria-label="Okruszki">' . implode('<span aria-hidden="true">/</span>', $items) . '</nav>';
    }

    /** @param list<PageAction> $actions */
    private function actions(array $actions): string
    {
        $html = '';
        foreach ($actions as $action) {
            $class = in_array($action->intent, [ActionIntent::Submit, ActionIntent::StartJob], true) ? 'primary-action' : 'secondary-action';
            $tag = $action->url === null ? 'button' : 'a';
            $href = $action->url === null ? ' type="button"' : ' href="' . Html::escape($action->url) . '"';
            $confirm = $action->confirmationRequired ? ' data-confirmation-required="true"' : '';
            $html .= '<' . $tag . ' class="' . $class . '"' . $href . $confirm . '>' . Html::escape($action->label) . '</' . $tag . '>';
        }
        return $html;
    }

    private function region(PageDefinition $page, string $name, string $class): string
    {
        $components = $page->region($name);
        return $components === [] ? '' : '<section class="' . $class . '">' . $this->renderers->renderMany($components) . '</section>';
    }
}
