<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\RequiredPackagePolicy;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Operation\PackageOperator;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final class PackageOperatorTest extends TestCase
{
    public function testPlanRejectsRequiredPackageAndActiveDependents(): void
    {
        $registry = new InMemoryPackageRegistry();
        $registry->add($this->release('system.themes', '1.0.0', PackageState::Active));
        $registry->setActive('system.themes', '1.0.0');
        $policy = new RequiredPackagePolicy(['system.themes']);
        $manager = new PackageLifecycleManager($registry, new PackageLifecycle(), $policy);
        $operator = new PackageOperator($registry, $manager, $policy);
        self::assertFalse($operator->plan('disable', 'system.themes')->executable());
        self::assertFalse($operator->plan('uninstall', 'system.themes', '1.0.0')->executable());
        $this->expectException(\DomainException::class);
        $manager->transition('system.themes', '1.0.0', PackageState::Disabled);
    }

    public function testDisableRequiresNoActiveDependentsAndUninstallPreservesOtherReleases(): void
    {
        $registry = new InMemoryPackageRegistry();
        $registry->add($this->release('fixture.base', '1.0.0', PackageState::Active));
        $registry->add($this->release('fixture.dependent', '1.0.0', PackageState::Active,
            ['fixture.base' => '^1.0']));
        $registry->setActive('fixture.base', '1.0.0');
        $registry->setActive('fixture.dependent', '1.0.0');
        $policy = new RequiredPackagePolicy();
        $manager = new PackageLifecycleManager($registry, new PackageLifecycle(), $policy);
        $operator = new PackageOperator($registry, $manager, $policy);
        self::assertFalse($operator->plan('disable', 'fixture.base')->executable());
        try {
            $manager->transition('fixture.base', '1.0.0', PackageState::Disabled);
            self::fail('Direct lifecycle call must reject active dependents.');
        } catch (\DomainException) {
            self::assertSame('1.0.0', $registry->active('fixture.base')?->manifest->version);
        }
        $disableDependent = $operator->plan('disable', 'fixture.dependent');
        self::assertTrue($disableDependent->executable());
        $operator->apply('disable', 'fixture.dependent', '', $disableDependent->checksum);
        self::assertNull($registry->active('fixture.dependent'));
        $disableBase = $operator->plan('disable', 'fixture.base');
        $operator->apply('disable', 'fixture.base', '', $disableBase->checksum);
        $uninstall = $operator->plan('uninstall', 'fixture.base', '1.0.0');
        $operator->apply('uninstall', 'fixture.base', '1.0.0', $uninstall->checksum);
        self::assertNull($registry->find('fixture.base', '1.0.0'));
        self::assertNotNull($registry->find('fixture.dependent', '1.0.0'));
    }

    public function testRollbackUsesRetainedReleaseAndStalePlanIsRejected(): void
    {
        $registry = new InMemoryPackageRegistry();
        $registry->add($this->release('fixture.base', '1.0.0', PackageState::Active));
        $registry->add($this->release('fixture.base', '1.1.0', PackageState::Active));
        $registry->setActive('fixture.base', '1.1.0');
        $policy = new RequiredPackagePolicy();
        $operator = new PackageOperator($registry,
            new PackageLifecycleManager($registry, new PackageLifecycle(), $policy), $policy);
        $rollback = $operator->plan('rollback', 'fixture.base', '1.0.0');
        self::assertTrue($rollback->executable());
        $operator->apply('rollback', 'fixture.base', '1.0.0', $rollback->checksum);
        self::assertSame('1.0.0', $registry->active('fixture.base')?->manifest->version);
        $this->expectException(\LogicException::class);
        $operator->apply('rollback', 'fixture.base', '1.0.0', $rollback->checksum);
    }

    /** @param array<string, string> $requires */
    private function release(string $id, string $version, PackageState $state, array $requires = []): PackageRelease
    {
        return new PackageRelease(new PackageManifest(1, $id, $id, $version, PackageType::Module,
            '^1.0', [], $requires, [], 'Fixture\\Module'), '/packages/' . $id . '/' . $version, $state);
    }
}
