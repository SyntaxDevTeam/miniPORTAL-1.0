<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

enum InputType: string
{
    case Text = 'text';
    case Email = 'email';
    case Password = 'password';
    case Url = 'url';
    case Search = 'search';
}
