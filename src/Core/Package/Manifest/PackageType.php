<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Manifest;

enum PackageType: string
{
    case Module = 'module';
    case Library = 'library';
    case Provider = 'provider';
    case Theme = 'theme';
}
