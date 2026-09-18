<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\DependencyInjection;

final class CircularDependency extends ContainerException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Circular service dependency detected while resolving %s.', $id));
    }
}
