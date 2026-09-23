<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Jobs\Worker;

use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobHandler;

final class JobHandlerRegistry
{
    /** @var array<string, JobHandler> */
    private array $handlers = [];

    public function register(string $packageId, string $name, JobHandler $handler): void
    {
        $key = $this->key($packageId, $name);
        if (isset($this->handlers[$key])) {
            throw new \LogicException(sprintf('Job handler %s is already registered.', $key));
        }
        $this->handlers[$key] = $handler;
    }

    public function get(string $packageId, string $name): ?JobHandler
    {
        return $this->handlers[$this->key($packageId, $name)] ?? null;
    }

    public function count(): int
    {
        return count($this->handlers);
    }

    private function key(string $packageId, string $name): string
    {
        if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $packageId) !== 1
            || preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name) !== 1) {
            throw new \InvalidArgumentException('Invalid job handler package or name.');
        }
        return $packageId . ':' . $name;
    }
}
