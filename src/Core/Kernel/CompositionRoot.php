<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Kernel;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\DependencyInjection\ServiceContainer;
use SyntaxDevTeam\MiniPortal\Core\Logging\ErrorLogLogger;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleDispatcher;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\DependencyResolver;
use SyntaxDevTeam\MiniPortal\Core\Package\Dependency\VersionConstraint;
use SyntaxDevTeam\MiniPortal\Core\Package\Discovery\PackageDiscovery;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;

final class CompositionRoot
{
    public function build(Runtime $runtime): ServiceContainer
    {
        $container = new ServiceContainer();

        $container->instance(Runtime::class, $runtime);
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
        $container->set(
            ModuleDispatcher::class,
            static fn (ServiceContainer $services): ModuleDispatcher => new ModuleDispatcher(
                self::service($services, Logger::class, Logger::class),
            ),
        );
        $container->set(Router::class, static fn (ServiceContainer $_): Router => new Router());

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
