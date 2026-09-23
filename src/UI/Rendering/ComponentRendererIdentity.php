<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Rendering;

use SyntaxDevTeam\MiniPortal\UI\Contract\Component;

final readonly class ComponentRendererIdentity
{
    public ?string $value;

    public function __construct(Component $component)
    {
        $this->value = $component->identity()?->value;
    }
}
