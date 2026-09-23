<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Contract;

use SyntaxDevTeam\MiniPortal\UI\Rendering\RendererRegistry;

interface Theme extends PageRenderer
{
    public function id(): string;

    public function uiApiConstraint(): string;

    public function supportsLayout(string $layoutRole): bool;

    public function renderers(): RendererRegistry;
}
