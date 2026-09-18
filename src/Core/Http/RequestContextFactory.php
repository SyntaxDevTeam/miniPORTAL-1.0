<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

use SyntaxDevTeam\MiniPortal\Core\Capability\CapabilityRegistry;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class RequestContextFactory
{
    public function __construct(
        private CorrelationId $correlationId,
        private CapabilityRegistry $capabilities,
    ) {
    }

    /**
     * @param array<string, string> $clientHints
     * @param list<string> $permissions
     */
    public function create(
        ?string $principalId = null,
        string $locale = 'en',
        string $timezone = 'UTC',
        array $clientHints = [],
        array $permissions = [],
        ?string $csrfToken = null,
    ): RequestContext {
        return new RequestContext(
            correlationId: $this->correlationId,
            principalId: $principalId,
            locale: $locale,
            timezone: $timezone,
            clientHints: $clientHints,
            permissions: $permissions,
            csrfToken: $csrfToken,
            capabilityProviders: $this->capabilities->providerMap(),
        );
    }
}
