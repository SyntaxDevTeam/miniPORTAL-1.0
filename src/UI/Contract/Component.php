<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Contract;

use SyntaxDevTeam\MiniPortal\UI\Model\ComponentIdentity;

interface Component
{
    public static function componentType(): string;

    public function identity(): ?ComponentIdentity;

    /** @return list<Component> */
    public function children(): array;
}
