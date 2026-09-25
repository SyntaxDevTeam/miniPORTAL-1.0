<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\ErrorState;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class ErrorStateRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof ErrorState) {
            throw new \LogicException('ErrorStateRenderer received an unsupported component.');
        }

        $description = $component->description === null
            ? ''
            : '<p class="mp-error-state__description">' . Html::escape($component->description) . '</p>';
        $errorId = $component->errorId === null
            ? ''
            : '<p class="mp-error-state__id">ID błędu: <code>' . Html::escape($component->errorId) . '</code></p>';

        return '<section class="mp-error-state" role="alert"' . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . '<h2 class="mp-error-state__title">' . Html::escape($component->title) . '</h2>'
            . $description . $errorId . '</section>';
    }
}
