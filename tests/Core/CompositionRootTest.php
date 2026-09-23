<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Capability\CapabilityRegistry;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContextFactory;
use SyntaxDevTeam\MiniPortal\Core\Kernel\CompositionRoot;
use SyntaxDevTeam\MiniPortal\Core\Kernel\Runtime;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local\LocalFilesystemProvider;
use SyntaxDevTeam\MiniPortal\Library\Audit\Contract\AuditSink;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Contract\JobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Provider\InMemoryJobQueue;
use SyntaxDevTeam\MiniPortal\Library\Jobs\Worker\JobWorker;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class CompositionRootTest extends TestCase
{
    public function testRegistersKernelServicesWithoutEagerResolution(): void
    {
        try {
            $container = (new CompositionRoot())->build(Runtime::boot('testing'));

            self::assertTrue($container->has(Cache::class));
            self::assertTrue($container->has(LocalFilesystemProvider::class));
            self::assertTrue($container->has(PdoDatabaseFactory::class));
            self::assertTrue($container->has(Clock::class));
            self::assertTrue($container->has(JobQueue::class));
            self::assertTrue($container->has(JobWorker::class));
            self::assertTrue($container->has(AuditSink::class));
            self::assertTrue($container->has(CapabilityRegistry::class));
            self::assertTrue($container->has(RequestContextFactory::class));
            self::assertTrue($container->has(PackageDiscovery::class));
            self::assertTrue($container->has(DependencyResolver::class));
            self::assertTrue($container->has(PackageRegistry::class));
            self::assertTrue($container->has(PackageLifecycleManager::class));
            self::assertTrue($container->has(PackagePreflightService::class));
            self::assertTrue($container->has(ModuleRegistrar::class));
            self::assertInstanceOf(Cache::class, $container->get(Cache::class));
            self::assertInstanceOf(LocalFilesystemProvider::class, $container->get(LocalFilesystemProvider::class));
            self::assertInstanceOf(PdoDatabaseFactory::class, $container->get(PdoDatabaseFactory::class));
            self::assertInstanceOf(InMemoryJobQueue::class, $container->get(JobQueue::class));
            self::assertInstanceOf(JobWorker::class, $container->get(JobWorker::class));
            self::assertInstanceOf(CapabilityRegistry::class, $container->get(CapabilityRegistry::class));
            self::assertInstanceOf(RequestContextFactory::class, $container->get(RequestContextFactory::class));
            self::assertInstanceOf(PackageDiscovery::class, $container->get(PackageDiscovery::class));
            self::assertSame(
                $container->get(PackageDiscovery::class),
                $container->get(PackageDiscovery::class),
            );
        } finally {
            restore_exception_handler();
        }
    }

    public function testRegistersCacheAsRuntimeCapability(): void
    {
        try {
            $container = (new CompositionRoot())->build(Runtime::boot('testing'));
            $registry = $container->get(CapabilityRegistry::class);
            $cache = $container->get(Cache::class);

            self::assertInstanceOf(CapabilityRegistry::class, $registry);
            self::assertInstanceOf(Cache::class, $cache);

            $registered = $registry->find('cache');

            self::assertNotNull($registered);
            self::assertSame('1.0.0', $registered->version);
            self::assertSame($cache, $registered->service);
            self::assertContains($registered->providerId, [
                'core.cache.apcu',
                'core.cache.array',
            ]);
        } finally {
            restore_exception_handler();
        }
    }

    public function testRegistersFilesystemProviderAsRuntimeCapability(): void
    {
        try {
            $container = (new CompositionRoot())->build(Runtime::boot('testing'));
            $registry = $container->get(CapabilityRegistry::class);
            $filesystem = $container->get(LocalFilesystemProvider::class);

            self::assertInstanceOf(CapabilityRegistry::class, $registry);
            self::assertInstanceOf(LocalFilesystemProvider::class, $filesystem);

            $registered = $registry->find('filesystem');

            self::assertNotNull($registered);
            self::assertSame('1.0.0', $registered->version);
            self::assertSame('core.filesystem.local', $registered->providerId);
            self::assertSame($filesystem, $registered->service);
        } finally {
            restore_exception_handler();
        }
    }
}
