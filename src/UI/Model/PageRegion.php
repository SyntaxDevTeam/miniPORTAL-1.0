<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

final class PageRegion
{
    public const HEADER = 'header';
    public const PRIMARY_NAVIGATION = 'primary_navigation';
    public const SECONDARY_NAVIGATION = 'secondary_navigation';
    public const BREADCRUMBS = 'breadcrumbs';
    public const PAGE_HEADER = 'page_header';
    public const ACTIONS = 'actions';
    public const CONTENT = 'content';
    public const ASIDE = 'aside';
    public const FOOTER = 'footer';
    public const OVERLAYS = 'overlays';

    public static function validate(string $name): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Page region must be a semantic identifier.');
        }
    }
}
