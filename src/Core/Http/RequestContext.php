<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Http;

use DateTimeZone;
use Exception;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class RequestContext
{
    public CorrelationId $correlationId;
    public ?string $principalId;
    public string $locale;
    public string $timezone;

    /** @var array<string, string> */
    public array $clientHints;

    /** @var array<string, true> */
    public array $permissions;

    public ?string $csrfToken;

    /** @var array<string, string> */
    public array $capabilityProviders;

    /**
     * @param array<string, string> $clientHints
     * @param list<string> $permissions
     * @param array<string, string> $capabilityProviders
     */
    public function __construct(
        CorrelationId $correlationId,
        ?string $principalId = null,
        string $locale = 'en',
        string $timezone = 'UTC',
        array $clientHints = [],
        array $permissions = [],
        ?string $csrfToken = null,
        array $capabilityProviders = [],
    ) {
        $normalizedPrincipal = $principalId === null ? null : trim($principalId);
        if ($normalizedPrincipal === '') {
            throw new \InvalidArgumentException('Principal ID cannot be empty when present.');
        }

        $normalizedLocale = str_replace('_', '-', trim($locale));
        if (preg_match('/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})*$/', $normalizedLocale) !== 1) {
            throw new \InvalidArgumentException('Locale must be a valid language tag.');
        }

        $normalizedTimezone = trim($timezone);
        try {
            new DateTimeZone($normalizedTimezone);
        } catch (Exception $exception) {
            throw new \InvalidArgumentException('Timezone must be a valid timezone identifier.', previous: $exception);
        }

        $permissionSet = [];
        foreach ($permissions as $permission) {
            $normalizedPermission = trim($permission);
            if ($normalizedPermission === '') {
                throw new \InvalidArgumentException('Permission names cannot be empty.');
            }

            $permissionSet[$normalizedPermission] = true;
        }

        $normalizedCsrfToken = $csrfToken === null ? null : trim($csrfToken);
        if ($normalizedCsrfToken === '') {
            throw new \InvalidArgumentException('CSRF token cannot be empty when present.');
        }

        $this->correlationId = $correlationId;
        $this->principalId = $normalizedPrincipal;
        $this->locale = $normalizedLocale;
        $this->timezone = $normalizedTimezone;
        $this->clientHints = self::normalizeMap($clientHints, 'Client hint');
        $this->permissions = $permissionSet;
        $this->csrfToken = $normalizedCsrfToken;
        $this->capabilityProviders = self::normalizeMap($capabilityProviders, 'Capability provider');
    }

    public function isAuthenticated(): bool
    {
        return $this->principalId !== null;
    }

    public function hasPermission(string $permission): bool
    {
        return isset($this->permissions[$permission]) || isset($this->permissions['*']);
    }

    public function capabilityProvider(string $capability): ?string
    {
        return $this->capabilityProviders[$capability] ?? null;
    }

    /**
     * @param array<string, string> $values
     * @return array<string, string>
     */
    private static function normalizeMap(array $values, string $label): array
    {
        $normalized = [];

        foreach ($values as $key => $value) {
            $normalizedKey = trim($key);
            $normalizedValue = trim($value);

            if ($normalizedKey === '' || $normalizedValue === '') {
                throw new \InvalidArgumentException(sprintf('%s keys and values cannot be empty.', $label));
            }

            $normalized[$normalizedKey] = $normalizedValue;
        }

        ksort($normalized);

        return $normalized;
    }
}
