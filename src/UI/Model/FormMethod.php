<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

enum FormMethod: string
{
    case Get = 'get';
    case Post = 'post';
}
