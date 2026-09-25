<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\PermissionDeniedState;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class PermissionDeniedStateRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof PermissionDeniedState) {
            throw new \LogicException('PermissionDeniedStateRenderer received an unsupported component.');
        }

        $description = $component->description === null
            ? ''
            : '<p class="mp-permission-denied-state__description">' . Html::escape($component->description) . '</p>';
        $permission = $component->requiredPermission === null
            ? ''
            : '<p class="mp-permission-denied-state__permission">Wymagane uprawnienie: <code>'
                . Html::escape($component->requiredPermission) . '</code></p>';

        return '<section class="mp-permission-denied-state" role="alert"'
            . Html::identityAttribute(new ComponentRendererIdentity($component)) . '>'
            . '<h2 class="mp-permission-denied-state__title">' . Html::escape($component->title) . '</h2>'
            . $description . $permission . '</section>';
    }
}
