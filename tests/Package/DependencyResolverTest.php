<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyIssueCode;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;

final class DependencyResolverTest extends TestCase
{
    public function testResolvesModuleAndCapabilityDependencies(): void
    {
        $filesystem = $this->manifest(
            id: 'filesystem.local',
            type: PackageType::Provider,
            provides: ['filesystem' => '1.2.0'],
        );
        $files = $this->manifest(
            id: 'files',
            requiresCapabilities: ['filesystem' => '^1.0'],
        );
        $console = $this->manifest(
            id: 'console',
            requiresModules: ['files' => '^1.0'],
        );

        $resolution = $this->resolver()->resolve([$console, $files, $filesystem], '1.0.0');

        self::assertTrue($resolution->isSuccessful());
        self::assertSame('filesystem.local', $resolution->capabilityProviders['filesystem'] ?? null);
        self::assertLessThan(
            array_search('console', $resolution->loadOrder, true),
            array_search('files', $resolution->loadOrder, true),
        );
    }

    public function testReportsMissingCapabilityAndCycleWithoutThrowing(): void
    {
        $first = $this->manifest(
            id: 'first',
            requiresModules: ['second' => '^1.0'],
            requiresCapabilities: ['realtime' => '^1.0'],
        );
        $second = $this->manifest(
            id: 'second',
            requiresModules: ['first' => '^1.0'],
        );

        $resolution = $this->resolver()->resolve([$first, $second], '1.0.0');
        $codes = array_map(
            static fn ($issue): DependencyIssueCode => $issue->code,
            $resolution->issues,
        );

        self::assertFalse($resolution->isSuccessful());
        self::assertContains(DependencyIssueCode::MissingCapability, $codes);
        self::assertContains(DependencyIssueCode::DependencyCycle, $codes);
    }

    private function resolver(): DependencyResolver
    {
        return new DependencyResolver(new VersionConstraint());
    }

    /**
     * @param array<string, string> $requiresModules
     * @param array<string, string> $requiresCapabilities
     * @param array<string, string> $provides
     */
    private function manifest(
        string $id,
        PackageType $type = PackageType::Module,
        array $requiresModules = [],
        array $requiresCapabilities = [],
        array $provides = [],
    ): PackageManifest {
        return new PackageManifest(
            schema: 1,
            id: $id,
            name: $id,
            version: '1.0.0',
            type: $type,
            coreConstraint: '^1.0',
            requiredCapabilities: $requiresCapabilities,
            requiredModules: $requiresModules,
            providedCapabilities: $provides,
            entrypoint: $type === PackageType::Library || $type === PackageType::Theme ? null : 'Fixture\\Entry',
        );
    }
}
