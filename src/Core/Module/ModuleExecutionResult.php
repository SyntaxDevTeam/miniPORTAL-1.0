<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

final readonly class ModuleExecutionResult
{
    private function __construct(
        public string $moduleId,
        public ModuleExecutionPhase $phase,
        public bool $successful,
        public ?string $errorId,
    ) {
    }

    public static function success(string $moduleId, ModuleExecutionPhase $phase): self
    {
        return new self($moduleId, $phase, true, null);
    }

    public static function failure(string $moduleId, ModuleExecutionPhase $phase, string $errorId): self
    {
        return new self($moduleId, $phase, false, $errorId);
    }
}
