<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

final readonly class ModuleExecutionResult
{
    private function __construct(
        public string $moduleId,
        public bool $successful,
        public ?string $errorId,
    ) {
    }

    public static function success(string $moduleId): self
    {
        return new self($moduleId, true, null);
    }

    public static function failure(string $moduleId, string $errorId): self
    {
        return new self($moduleId, false, $errorId);
    }
}
