<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Capability;

final class CapabilityRegistry
{
    /** @var array<string, RegisteredCapability> */
    private array $registrations = [];

    public function register(RegisteredCapability $capability): void
    {
        if (isset($this->registrations[$capability->name])) {
            $current = $this->registrations[$capability->name];

            throw new \LogicException(sprintf(
                'Capability %s is already provided by %s@%s.',
                $capability->name,
                $current->providerId,
                $current->version,
            ));
        }

        $this->registrations[$capability->name] = $capability;
    }

    public function find(string $name): ?RegisteredCapability
    {
        return $this->registrations[$name] ?? null;
    }

    /** @return list<RegisteredCapability> */
    public function all(): array
    {
        $registrations = $this->registrations;
        ksort($registrations);

        return array_values($registrations);
    }

    /** @return array<string, string> */
    public function versions(): array
    {
        $versions = [];

        foreach ($this->all() as $capability) {
            $versions[$capability->name] = $capability->version;
        }

        return $versions;
    }

    /** @return array<string, string> */
    public function providerMap(): array
    {
        $providers = [];

        foreach ($this->all() as $capability) {
            $providers[$capability->name] = $capability->providerId;
        }

        return $providers;
    }
}
