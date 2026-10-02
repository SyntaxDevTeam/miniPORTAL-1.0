<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle;

/** IDs come from the trusted Core distribution, never from a package manifest. */
final readonly class RequiredPackagePolicy
{
    /** @var array<string, true> */
    private array $required;

    /** @param list<string> $packageIds */
    public function __construct(array $packageIds = [])
    {
        $required = [];
        foreach ($packageIds as $id) {
            if (preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/D', $id) !== 1) {
                throw new \InvalidArgumentException('Required package ID is invalid.');
            }
            $required[$id] = true;
        }
        $this->required = $required;
    }

    public function isRequired(string $packageId): bool
    {
        return isset($this->required[$packageId]);
    }

    public function assertTransition(string $packageId, PackageState $from, PackageState $to): void
    {
        if ($this->isRequired($packageId)
            && in_array($from, [PackageState::Ready, PackageState::Active, PackageState::Degraded], true)
            && in_array($to, [PackageState::Disabled, PackageState::Failed], true)) {
            throw new \DomainException(sprintf('Required package %s cannot be disabled.', $packageId));
        }
    }
}
