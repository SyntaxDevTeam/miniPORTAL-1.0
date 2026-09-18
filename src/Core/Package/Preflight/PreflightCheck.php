<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

final readonly class PreflightCheck
{
    public function __construct(
        public string $name,
        public PreflightCheckStatus $status,
        public string $detail,
    ) {
    }
}
