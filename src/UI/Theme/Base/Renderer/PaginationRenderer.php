<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\Pagination;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class PaginationRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof Pagination) {
            throw new \LogicException('PaginationRenderer received an unsupported component.');
        }

        $previous = $component->previousUrl === null
            ? ''
            : '<a class="mp-pagination__previous" rel="prev" href="' . Html::escape($component->previousUrl) . '">Poprzednia</a>';
        $next = $component->nextUrl === null
            ? ''
            : '<a class="mp-pagination__next" rel="next" href="' . Html::escape($component->nextUrl) . '">Następna</a>';

        return '<nav class="mp-pagination" aria-label="' . Html::escape($component->label) . '"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . $previous . '<span class="mp-pagination__status" aria-current="page">Strona '
            . $component->currentPage . ' z ' . $component->totalPages . '</span>' . $next . '</nav>';
    }
}
