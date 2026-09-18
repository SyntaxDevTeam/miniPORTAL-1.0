<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class ModuleContext
{
    public function __construct(public CorrelationId $correlationId)
    {
    }
}
