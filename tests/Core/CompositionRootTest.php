<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;

final class CompositionRootTest extends TestCase
{
    public function testRegistersKernelServicesWithoutEagerResolution(): void
    {
        try {
            $container = (new CompositionRoot())->build(Runtime::boot('testing'));

            self::assertTrue($container->has(PackageDiscovery::class));
            self::assertTrue($container->has(DependencyResolver::class));
            self::assertTrue($container->has(PackageRegistry::class));
            self::assertTrue($container->has(PackageLifecycleManager::class));
            self::assertTrue($container->has(PackagePreflightService::class));
            self::assertTrue($container->has(ModuleRegistrar::class));
            self::assertInstanceOf(PackageDiscovery::class, $container->get(PackageDiscovery::class));
            self::assertSame(
                $container->get(PackageDiscovery::class),
                $container->get(PackageDiscovery::class),
            );
        } finally {
            restore_exception_handler();
        }
    }
}
