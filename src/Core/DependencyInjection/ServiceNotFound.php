<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\DependencyInjection;

final class ServiceNotFound extends ContainerException
{
    public static function forId(string $id): self
    {
        return new self(sprintf('Service %s is not registered.', $id));
    }
}
