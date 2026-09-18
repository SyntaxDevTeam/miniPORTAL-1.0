<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Module\Registration\BufferedModuleRouteRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class ModuleRegistrar
{
    public function __construct(
        private Router $router,
        private Logger $logger,
    ) {
    }

    public function register(string $moduleId, Module $module): ModuleExecutionResult
    {
        $routes = new BufferedModuleRouteRegistrar($moduleId);
        $registration = new ModuleRegistration($routes);

        try {
            $module->register($registration);
            $this->router->addBatch($routes->definitions());

            return ModuleExecutionResult::success($moduleId, ModuleExecutionPhase::Registration);
        } catch (\Throwable $throwable) {
            $errorId = (string) CorrelationId::generate();
            $this->logger->error('Module registration failed.', [
                'module_id' => $moduleId,
                'phase' => ModuleExecutionPhase::Registration->value,
                'error_id' => $errorId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return ModuleExecutionResult::failure(
                $moduleId,
                ModuleExecutionPhase::Registration,
                $errorId,
            );
        }
    }
}
