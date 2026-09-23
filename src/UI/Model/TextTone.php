<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Model;

enum TextTone: string
{
    case Default = 'default';
    case Muted = 'muted';
    case Positive = 'positive';
    case Critical = 'critical';
}
