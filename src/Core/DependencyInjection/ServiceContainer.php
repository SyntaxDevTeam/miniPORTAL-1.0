<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\DependencyInjection;

use Closure;

final class ServiceContainer
{
    /** @var array<string, Closure(self): object> */
    private array $factories = [];

    /** @var array<string, object> */
    private array $instances = [];

    /** @var array<string, true> */
    private array $resolving = [];

    /** @param Closure(self): object $factory */
    public function set(string $id, Closure $factory): void
    {
        if ($id === '') {
            throw new ContainerException('Service ID cannot be empty.');
        }

        if (isset($this->instances[$id])) {
            throw new ContainerException(sprintf('Service %s is already instantiated.', $id));
        }

        $this->factories[$id] = $factory;
    }

    public function instance(string $id, object $service): void
    {
        if ($id === '') {
            throw new ContainerException('Service ID cannot be empty.');
        }

        $this->instances[$id] = $service;
        unset($this->factories[$id]);
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) || isset($this->factories[$id]);
    }

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $factory = $this->factories[$id] ?? null;
        if ($factory === null) {
            throw ServiceNotFound::forId($id);
        }

        if (isset($this->resolving[$id])) {
            throw CircularDependency::forId($id);
        }

        $this->resolving[$id] = true;

        try {
            $service = $factory($this);
            $this->instances[$id] = $service;
            return $service;
        } finally {
            unset($this->resolving[$id]);
        }
    }
}
