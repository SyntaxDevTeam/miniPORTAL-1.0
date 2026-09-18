<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Diagnostics;

final readonly class DoctorCheck
{
    public function __construct(
        public string $name,
        public bool $ok,
        public string $detail,
    ) {
    }
}
