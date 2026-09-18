<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

final readonly class PreflightCheckResult
{
    public function __construct(
        public string $checkId,
        public PreflightCheckStatus $status,
        public string $message,
    ) {
        if (trim($checkId) === '') {
            throw new \InvalidArgumentException('Preflight check ID cannot be empty.');
        }
    }

    public static function passed(string $checkId, string $message): self
    {
        return new self($checkId, PreflightCheckStatus::Passed, $message);
    }

    public static function warning(string $checkId, string $message): self
    {
        return new self($checkId, PreflightCheckStatus::Warning, $message);
    }

    public static function failed(string $checkId, string $message): self
    {
        return new self($checkId, PreflightCheckStatus::Failed, $message);
    }
}
