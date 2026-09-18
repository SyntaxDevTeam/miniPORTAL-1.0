<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\MetadataPreflight;
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
            self::assertTrue($container->has(MetadataPreflight::class));
            self::assertInstanceOf(PackageDiscovery::class, $container->get(PackageDiscovery::class));
            self::assertInstanceOf(PackageRegistry::class, $container->get(PackageRegistry::class));
            self::assertSame(
                $container->get(PackageDiscovery::class),
                $container->get(PackageDiscovery::class),
            );
        } finally {
            restore_exception_handler();
        }
    }
}
