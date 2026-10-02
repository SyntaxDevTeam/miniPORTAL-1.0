<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Module\Registration\BufferedModuleRouteRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Routing\RouteDefinition;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Core\Widget\BufferedWidgetRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetCatalog;
use SyntaxDevTeam\MiniPortal\Core\Api\BufferedApiRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Api\ServiceApiGateway;
use SyntaxDevTeam\MiniPortal\Core\Navigation\BufferedNavigationRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Navigation\NavigationCatalog;

/** Mounts an already trusted Module instance only for its active release. */
final readonly class ActiveModuleMount
{
    public function __construct(
        private PackageRegistry $registry,
        private Router $router,
        private Logger $logger,
        private ?WidgetCatalog $widgets = null,
        private ?ServiceApiGateway $apiGateway = null,
        private ?NavigationCatalog $navigation = null,
    ) {
    }

    public function mount(string $moduleId, Module $module, ModuleContext $context): ?ModuleExecutionResult
    {
        try {
            $release = $this->registry->active($moduleId);
        } catch (\Throwable $exception) {
            return $this->failure($moduleId, ModuleExecutionPhase::Registration, $context, $exception);
        }
        if ($release === null || $release->manifest->type !== PackageType::Module) {
            return null;
        }
        $version = $release->manifest->version;
        $routes = new BufferedModuleRouteRegistrar($moduleId);
        $widgetRegistrar = new BufferedWidgetRegistrar();
        $apiRegistrar = $this->apiGateway === null ? null : new BufferedApiRegistrar($moduleId, $this->apiGateway);
        $navigationRegistrar = $this->navigation === null ? null : new BufferedNavigationRegistrar($moduleId);
        try {
            $module->register(new ModuleRegistration($routes, $widgetRegistrar, $apiRegistrar, $navigationRegistrar));
        } catch (\Throwable $exception) {
            return $this->failure($moduleId, ModuleExecutionPhase::Registration, $context, $exception);
        }
        try {
            $module->boot($context);
        } catch (\Throwable $exception) {
            return $this->failure($moduleId, ModuleExecutionPhase::Boot, $context, $exception);
        }
        try {
            $this->widgets?->registerModule($moduleId, $widgetRegistrar->providers());
            $this->navigation?->registerModule($moduleId, $navigationRegistrar?->items() ?? []);
            $this->router->addBatch(array_map(
                fn (RouteDefinition $definition): RouteDefinition => new RouteDefinition(
                    $definition->method,
                    $definition->path,
                    $definition->name,
                    function (Request $request) use ($definition, $moduleId, $version): Response {
                        try {
                            $current = $this->registry->active($moduleId);
                            if ($current === null || $current->manifest->version !== $version) {
                                return Response::text('Not Found', 404)->withPrivateNoStore();
                            }
                            return ($definition->handler)($request);
                        } catch (\Throwable $exception) {
                            $errorId = (string) CorrelationId::generate();
                            $this->logger->error('Module route failed.', [
                                'module_id' => $moduleId,
                                'route' => $definition->name,
                                'error_id' => $errorId,
                                'request_id' => ($request->context === null ? $errorId : (string) $request->context->correlationId),
                                'exception' => $exception::class,
                                'message' => $exception->getMessage(),
                            ]);
                            return Response::text('Module unavailable. Error ID: ' . $errorId, 503)
                                ->withPrivateNoStore();
                        }
                    },
                ),
                [...$routes->definitions(), ...($apiRegistrar?->definitions() ?? [])],
            ));
        } catch (\Throwable $exception) {
            $this->widgets?->unregisterModule($moduleId);
            $this->navigation?->unregisterModule($moduleId);
            return $this->failure($moduleId, ModuleExecutionPhase::Registration, $context, $exception);
        }

        return ModuleExecutionResult::success($moduleId, ModuleExecutionPhase::Boot);
    }

    private function failure(
        string $moduleId,
        ModuleExecutionPhase $phase,
        ModuleContext $context,
        \Throwable $exception,
    ): ModuleExecutionResult {
        $errorId = (string) CorrelationId::generate();
        $this->logger->error('Module mount failed.', [
            'module_id' => $moduleId,
            'phase' => $phase->value,
            'error_id' => $errorId,
            'request_id' => (string) $context->correlationId,
            'exception' => $exception::class,
            'message' => $exception->getMessage(),
        ]);
        return ModuleExecutionResult::failure($moduleId, $phase, $errorId);
    }
}
