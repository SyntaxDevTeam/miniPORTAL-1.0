<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Dependency;

use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;

final readonly class DependencyResolver
{
    public function __construct(private VersionConstraint $constraints)
    {
    }

    /**
     * @param list<PackageManifest> $packages
     * @param array<string, string> $builtInCapabilities
     */
    public function resolve(array $packages, string $coreVersion, array $builtInCapabilities = []): DependencyResolution
    {
        /** @var array<string, PackageManifest> $byId */
        $byId = [];
        /** @var list<DependencyIssue> $issues */
        $issues = [];

        foreach ($packages as $package) {
            if (isset($byId[$package->id])) {
                $issues[] = new DependencyIssue(
                    DependencyIssueCode::DuplicatePackage,
                    $package->id,
                    $package->id,
                    sprintf('Package ID %s is declared more than once.', $package->id),
                );
                continue;
            }

            $byId[$package->id] = $package;
        }

        /** @var array<string, array<string, string>> $capabilityCandidates */
        $capabilityCandidates = [];
        foreach ($builtInCapabilities as $capability => $version) {
            $capabilityCandidates[$capability]['@core'] = $version;
        }
        foreach ($byId as $package) {
            foreach ($package->providedCapabilities as $capability => $version) {
                $capabilityCandidates[$capability][$package->id] = $version;
            }
        }

        /** @var array<string, string> $selectedProviders */
        $selectedProviders = [];

        foreach ($byId as $package) {
            $this->checkConstraint(
                $package,
                'core',
                $coreVersion,
                $package->coreConstraint,
                DependencyIssueCode::CoreVersionMismatch,
                $issues,
            );

            foreach ($package->requiredModules as $dependencyId => $constraint) {
                $dependency = $byId[$dependencyId] ?? null;
                if ($dependency === null) {
                    $issues[] = new DependencyIssue(
                        DependencyIssueCode::MissingModule,
                        $package->id,
                        $dependencyId,
                        sprintf('Required module %s is not available.', $dependencyId),
                    );
                    continue;
                }

                $this->checkConstraint(
                    $package,
                    $dependencyId,
                    $dependency->version,
                    $constraint,
                    DependencyIssueCode::ModuleVersionMismatch,
                    $issues,
                );
            }

            foreach ($package->requiredCapabilities as $capability => $constraint) {
                $candidates = $capabilityCandidates[$capability] ?? [];
                if ($candidates === []) {
                    $issues[] = new DependencyIssue(
                        DependencyIssueCode::MissingCapability,
                        $package->id,
                        $capability,
                        sprintf('Required capability %s has no provider.', $capability),
                    );
                    continue;
                }

                $provider = $this->selectCapabilityProvider($package, $capability, $constraint, $candidates, $issues);
                if ($provider !== null) {
                    $selectedProviders[$capability] ??= $provider;
                }
            }
        }

        $loadOrder = $this->topologicalOrder($byId, $issues);

        return new DependencyResolution($loadOrder, $selectedProviders, $issues);
    }

    /**
     * @param list<DependencyIssue> $issues
     */
    private function checkConstraint(
        PackageManifest $package,
        string $subject,
        string $actualVersion,
        string $constraint,
        DependencyIssueCode $mismatchCode,
        array &$issues,
    ): void {
        try {
            $matches = $this->constraints->matches($actualVersion, $constraint);
        } catch (\InvalidArgumentException) {
            $issues[] = new DependencyIssue(
                DependencyIssueCode::UnsupportedConstraint,
                $package->id,
                $subject,
                sprintf('Unsupported version constraint %s.', $constraint),
            );
            return;
        }

        if (!$matches) {
            $issues[] = new DependencyIssue(
                $mismatchCode,
                $package->id,
                $subject,
                sprintf('Required %s %s, available version is %s.', $subject, $constraint, $actualVersion),
            );
        }
    }

    /**
     * @param array<string, string> $candidates
     * @param list<DependencyIssue> $issues
     */
    private function selectCapabilityProvider(
        PackageManifest $package,
        string $capability,
        string $constraint,
        array $candidates,
        array &$issues,
    ): ?string {
        $constraintSupported = true;

        foreach ($candidates as $providerId => $version) {
            try {
                if ($this->constraints->matches($version, $constraint)) {
                    return $providerId;
                }
            } catch (\InvalidArgumentException) {
                $constraintSupported = false;
                break;
            }
        }

        $issues[] = new DependencyIssue(
            $constraintSupported ? DependencyIssueCode::CapabilityVersionMismatch : DependencyIssueCode::UnsupportedConstraint,
            $package->id,
            $capability,
            $constraintSupported
                ? sprintf('No provider of capability %s satisfies %s.', $capability, $constraint)
                : sprintf('Unsupported version constraint %s.', $constraint),
        );

        return null;
    }

    /**
     * @param array<string, PackageManifest> $packages
     * @param list<DependencyIssue> $issues
     * @return list<string>
     */
    private function topologicalOrder(array $packages, array &$issues): array
    {
        /** @var array<string, int> $states */
        $states = [];
        /** @var list<string> $order */
        $order = [];
        /** @var list<string> $stack */
        $stack = [];
        /** @var array<string, true> $reportedCycles */
        $reportedCycles = [];

        $visit = function (string $packageId) use (&$visit, &$states, &$order, &$stack, &$issues, &$reportedCycles, $packages): void {
            $state = $states[$packageId] ?? 0;
            if ($state === 2) {
                return;
            }

            if ($state === 1) {
                $start = array_search($packageId, $stack, true);
                $cycle = $start === false ? [$packageId] : array_slice($stack, $start);
                $cycle[] = $packageId;
                $cycleKey = implode('->', $cycle);

                if (!isset($reportedCycles[$cycleKey])) {
                    $reportedCycles[$cycleKey] = true;
                    $issues[] = new DependencyIssue(
                        DependencyIssueCode::DependencyCycle,
                        $packageId,
                        $packageId,
                        'Dependency cycle detected: ' . implode(' -> ', $cycle),
                    );
                }
                return;
            }

            $states[$packageId] = 1;
            $stack[] = $packageId;

            foreach ($packages[$packageId]->requiredModules as $dependencyId => $_constraint) {
                if (isset($packages[$dependencyId])) {
                    $visit($dependencyId);
                }
            }

            array_pop($stack);
            $states[$packageId] = 2;
            $order[] = $packageId;
        };

        foreach (array_keys($packages) as $packageId) {
            $visit($packageId);
        }

        return $order;
    }
}
