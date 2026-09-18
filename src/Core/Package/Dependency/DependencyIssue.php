<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Dependency;

final readonly class DependencyIssue
{
    public function __construct(
        public DependencyIssueCode $code,
        public string $packageId,
        public string $subject,
        public string $message,
    ) {
    }
}
