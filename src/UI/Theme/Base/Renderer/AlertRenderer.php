<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Base\Renderer;

use SyntaxDevTeam\MiniPortal\UI\Component\Alert;
use SyntaxDevTeam\MiniPortal\UI\Contract\Component;
use SyntaxDevTeam\MiniPortal\UI\Model\AlertSeverity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRenderer;
use SyntaxDevTeam\MiniPortal\UI\Rendering\ComponentRendererIdentity;
use SyntaxDevTeam\MiniPortal\UI\Rendering\Html;
use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

final class AlertRenderer implements ComponentRenderer
{
    public function render(Component $component, RendererRegistry $_registry): string
    {
        if (!$component instanceof Alert) {
            throw new \LogicException('AlertRenderer received an unsupported component.');
        }
        $role = $component->severity === AlertSeverity::Error ? 'alert' : 'status';
        $title = $component->title === null
            ? ''
            : '<strong class="mp-alert__title">' . Html::escape($component->title) . '</strong>';
        return sprintf(
            '<div class="mp-alert mp-alert--%s" role="%s"%s>%s<span>%s</span></div>',
            $component->severity->value,
            $role,
            Html::identityAttribute(new ComponentRendererIdentity($component)),
            $title,
            Html::escape($component->message),
        );
    }
}
