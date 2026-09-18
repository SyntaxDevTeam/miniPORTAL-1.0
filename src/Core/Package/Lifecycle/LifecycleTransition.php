<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

final readonly class LifecycleTransition
{
    public function __construct(
        public PackageLifecycleState $from,
        public PackageLifecycleState $to,
        public bool $allowed,
        public ?string $reason = null,
    ) {
    }

    public static function allowed(PackageLifecycleState $from, PackageLifecycleState $to): self
    {
        return new self($from, $to, true);
    }

    public static function rejected(PackageLifecycleState $from, PackageLifecycleState $to, string $reason): self
    {
        return new self($from, $to, false, $reason);
    }
}
