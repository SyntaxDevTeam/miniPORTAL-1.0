<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base;

use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final readonly class BasePageRenderer
{
    public function __construct(private RendererRegistry $renderers)
    {
    }

    public function render(PageDefinition $page, string $language): string
    {
        if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/D', $language) !== 1) {
            throw new \InvalidArgumentException('Page language must be a valid language tag.');
        }
        $regions = '';
        foreach ($page->regions as $name => $components) {
            if ($components === []) {
                continue;
            }
            $regions .= '<section class="mp-region mp-region--' . Html::escape($name) . '">' . $this->renderers->renderMany($components) . '</section>';
        }
        $actions = '';
        foreach ($page->actions as $action) {
            $actions .= $action->url === null
                ? '<button type="button">' . Html::escape($action->label) . '</button>'
                : '<a href="' . Html::escape($action->url) . '">' . Html::escape($action->label) . '</a>';
        }
        return '<!doctype html><html lang="' . Html::escape($language) . '"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1"><title>' . Html::escape($page->title) . '</title>'
            . '<style>body{font:16px/1.5 system-ui;margin:0 auto;max-width:70rem;padding:2rem;color:#17202a}main{display:grid;gap:1rem}.mp-card,.mp-alert,.mp-region{padding:1rem;border:1px solid #ccd6df;border-radius:.5rem}.mp-stack{display:grid;gap:1rem}.mp-text--muted{color:#526575}.mp-alert--error{border-color:#b42318}header{display:flex;justify-content:space-between;gap:1rem;align-items:center}header nav{display:flex;gap:.5rem}</style>'
            . '</head><body><header><h1>' . Html::escape($page->title) . '</h1><nav aria-label="Akcje strony">' . $actions . '</nav></header><main>' . $regions . '</main></body></html>';
    }
}
