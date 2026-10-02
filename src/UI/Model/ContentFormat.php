<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

enum ContentFormat: string
{
    case Markdown = 'markdown';
    case Html = 'html';
}
