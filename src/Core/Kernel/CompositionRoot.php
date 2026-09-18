<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Kernel;

use SyntaxDevTeam\MiniPortal\Core\Capability\CapabilityRegistry;
use SyntaxDevTeam\MiniPortal\Core\Capability\RegisteredCapability;
use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\DependencyInjection\ServiceContainer;
use SyntaxDevTeam\MiniPortal\Core\Event\EventDispatcher;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContextFactory;
use SyntaxDevTeam\MiniPortal\Core\Logging\ErrorLogLogger;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleDispatcher;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\DependencyPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\EntrypointPreflightCheck;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightRunner;
use SyntaxDevTeam\MiniPortal\Core\Package\Preflight\PackagePreflightService;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Library\Cache\Contract\Cache;
use SyntaxDevTeam\MiniPortal\Library\Cache\Provider\CacheProviderFactory;
use SyntaxDevTeam\MiniPortal\Library\Filesystem\Provider\Local\LocalFilesystemProvider;

final class CompositionRoot
{
    public function build(Runtime $runtime): ServiceContainer
    {
        $container = new ServiceContainer();

        $container->instance(Runtime::class, $runtime);
        $container->set(CacheProviderFactory::class, static fn (ServiceContainer $_): CacheProviderFactory => new CacheProviderFactory());
        $container->set(
            Cache::class,
            static fn (ServiceContainer $services): Cache => self::service(
                $services,
                CacheProviderFactory::class,
                CacheProviderFactory::class,
            )->create(),
        );
        $container->set(LocalFilesystemProvider::class, static fn (ServiceContainer $_): LocalFilesystemProvider => new LocalFilesystemProvider());
        $container->set(
            CapabilityRegistry::class,
            static function (ServiceContainer $services): CapabilityRegistry {
                $cache = self::service($services, Cache::class, Cache::class);
                $cacheFactory = self::service(
                    $services,
                    CacheProviderFactory::class,
                    CacheProviderFactory::class,
                );

                $registry = new CapabilityRegistry();
                $registry->register(new RegisteredCapability(
                    'cache',
                    '1.0.0',
                    $cacheFactory->providerId($cache),
                    $cache,
                ));

                $filesystem = self::service(
                    $services,
                    LocalFilesystemProvider::class,
                    LocalFilesystemProvider::class,
                );
                $registry->register(new RegisteredCapability(
                    'filesystem',
                    '1.0.0',
                    'core.filesystem.local',
                    $filesystem,
                ));

                return $registry;
            },
        );
        $container->set(
            RequestContextFactory::class,
            static fn (ServiceContainer $services): RequestContextFactory => new RequestContextFactory(
                self::service($services, Runtime::class, Runtime::class)->correlationId,
                self::service($services, CapabilityRegistry::class, CapabilityRegistry::class),
            ),
        );
        $container->set(Logger::class, static fn (ServiceContainer $_): ErrorLogLogger => new ErrorLogLogger());
        $container->set(ManifestParser::class, static fn (ServiceContainer $_): ManifestParser => new ManifestParser());
        $container->set(
            PackageDiscovery::class,
            static fn (ServiceContainer $services): PackageDiscovery => new PackageDiscovery(
                self::service($services, ManifestParser::class, ManifestParser::class),
            ),
        );
        $container->set(VersionConstraint::class, static fn (ServiceContainer $_): VersionConstraint => new VersionConstraint());
        $container->set(
            DependencyResolver::class,
            static fn (ServiceContainer $services): DependencyResolver => new DependencyResolver(
                self::service($services, VersionConstraint::class, VersionConstraint::class),
            ),
        );

        $container->set(PackageRegistry::class, static fn (ServiceContainer $_): InMemoryPackageRegistry => new InMemoryPackageRegistry());
        $container->set(PackageLifecycle::class, static fn (ServiceContainer $_): PackageLifecycle => new PackageLifecycle());
        $container->set(
            PackageLifecycleManager::class,
            static fn (ServiceContainer $services): PackageLifecycleManager => new PackageLifecycleManager(
                self::service($services, PackageRegistry::class, PackageRegistry::class),
                self::service($services, PackageLifecycle::class, PackageLifecycle::class),
            ),
        );
        $container->set(
            PackagePreflightRunner::class,
            static fn (ServiceContainer $_): PackagePreflightRunner => new PackagePreflightRunner([
                new DependencyPreflightCheck(),
                new EntrypointPreflightCheck(),
            ]),
        );
        $container->set(
            PackagePreflightService::class,
            static fn (ServiceContainer $services): PackagePreflightService => new PackagePreflightService(
                self::service($services, PackageRegistry::class, PackageRegistry::class),
                self::service($services, PackagePreflightRunner::class, PackagePreflightRunner::class),
                self::service($services, PackageLifecycleManager::class, PackageLifecycleManager::class),
            ),
        );

        $container->set(Router::class, static fn (ServiceContainer $_): Router => new Router());
        $container->set(
            ModuleRegistrar::class,
            static fn (ServiceContainer $services): ModuleRegistrar => new ModuleRegistrar(
                self::service($services, Router::class, Router::class),
                self::service($services, Logger::class, Logger::class),
            ),
        );
        $container->set(
            ModuleDispatcher::class,
            static fn (ServiceContainer $services): ModuleDispatcher => new ModuleDispatcher(
                self::service($services, Logger::class, Logger::class),
            ),
        );
        $container->set(
            EventDispatcher::class,
            static fn (ServiceContainer $services): EventDispatcher => new EventDispatcher(
                self::service($services, Logger::class, Logger::class),
            ),
        );

        return $container;
    }

    /**
     * @template T of object
     * @param class-string<T> $type
     * @return T
     */
    private static function service(ServiceContainer $services, string $id, string $type): object
    {
        $service = $services->get($id);
        if (!$service instanceof $type) {
            throw new \LogicException(sprintf('Service %s must be an instance of %s.', $id, $type));
        }

        return $service;
    }
}
