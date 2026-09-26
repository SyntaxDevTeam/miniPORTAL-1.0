<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;

final class IdentityProviderRegistry
{
    /** @var array<string, IdentityProvider> */
    private array $providers = [];

    /** @param iterable<IdentityProvider> $providers */
    public function __construct(iterable $providers = [])
    {
        foreach ($providers as $provider) {
            if (isset($this->providers[$provider->name()])) {
                throw new \InvalidArgumentException('Identity provider is registered more than once.');
            }
            $this->providers[$provider->name()] = $provider;
        }
    }

    /** @return list<IdentityProvider> */
    public function all(): array
    {
        return array_values($this->providers);
    }

    public function get(string $name): ?IdentityProvider
    {
        return $this->providers[$name] ?? null;
    }
}
