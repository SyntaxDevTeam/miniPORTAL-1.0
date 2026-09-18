<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

final class InvalidPackageTransition extends \DomainException
{
    public static function between(PackageState $from, PackageState $to): self
    {
        return new self(sprintf(
            'Package lifecycle transition %s -> %s is not allowed.',
            $from->value,
            $to->value,
        ));
    }
}
