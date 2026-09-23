<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Rendering;

final class Html
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function identityAttribute(ComponentRendererIdentity $identity): string
    {
        return $identity->value === null ? '' : ' data-component-id="' . self::escape($identity->value) . '"';
    }
}
