<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Preflight;

use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\RegisteredPackageRelease;

final readonly class MetadataPreflight
{
    public function __construct(private DependencyResolver $resolver)
    {
    }

    /**
     * @param list<PackageManifest> $availablePackages
     * @param array<string, string> $builtInCapabilities
     */
    public function run(
        RegisteredPackageRelease $release,
        PackageManifest $manifest,
        array $availablePackages,
        string $coreVersion,
        array $builtInCapabilities = [],
    ): PreflightReport {
        /** @var list<PreflightCheck> $checks */
        $checks = [];

        $checks[] = new PreflightCheck(
            'lifecycle_state',
            $release->state === PackageLifecycleState::Staged
                ? PreflightCheckStatus::Passed
                : PreflightCheckStatus::Failed,
            $release->state === PackageLifecycleState::Staged
                ? 'Release is staged and eligible for metadata preflight.'
                : sprintf('Expected staged release, current state is %s.', $release->state->value),
        );

        $identityMatches = $release->packageId === $manifest->id
            && $release->version === $manifest->version;

        $checks[] = new PreflightCheck(
            'manifest_identity',
            $identityMatches ? PreflightCheckStatus::Passed : PreflightCheckStatus::Failed,
            $identityMatches
                ? 'Registry release identity matches manifest.'
                : sprintf(
                    'Registry identity %s@%s does not match manifest %s@%s.',
                    $release->packageId,
                    $release->version,
                    $manifest->id,
                    $manifest->version,
                ),
        );

        $packages = $availablePackages;
        $containsCandidate = false;
        foreach ($packages as $available) {
            if ($available->id === $manifest->id && $available->version === $manifest->version) {
                $containsCandidate = true;
                break;
            }
        }
        if (!$containsCandidate) {
            $packages[] = $manifest;
        }

        $resolution = $this->resolver->resolve($packages, $coreVersion, $builtInCapabilities);

        $checks[] = new PreflightCheck(
            'dependencies',
            $resolution->isSuccessful() ? PreflightCheckStatus::Passed : PreflightCheckStatus::Failed,
            $resolution->isSuccessful()
                ? 'Dependency graph is compatible.'
                : sprintf('Dependency graph contains %d blocking issue(s).', count($resolution->issues)),
        );

        return new PreflightReport($release->packageId, $release->version, $checks);
    }
}
