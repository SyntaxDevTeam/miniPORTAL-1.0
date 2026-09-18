<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class ModuleDispatcher
{
    public function __construct(private Logger $logger)
    {
    }

    public function register(
        string $moduleId,
        Module $module,
        ModuleRegistration $registration,
        ModuleContext $context,
    ): ModuleExecutionResult {
        try {
            $module->register($registration);
            return ModuleExecutionResult::success($moduleId);
        } catch (\Throwable $throwable) {
            return $this->failure('registration', $moduleId, $context, $throwable);
        }
    }

    public function boot(string $moduleId, Module $module, ModuleContext $context): ModuleExecutionResult
    {
        try {
            $module->boot($context);
            return ModuleExecutionResult::success($moduleId);
        } catch (\Throwable $throwable) {
            return $this->failure('boot', $moduleId, $context, $throwable);
        }
    }

    private function failure(
        string $phase,
        string $moduleId,
        ModuleContext $context,
        \Throwable $throwable,
    ): ModuleExecutionResult {
        $errorId = (string) CorrelationId::generate();
        $this->logger->error(sprintf('Module %s failed.', $phase), [
            'phase' => $phase,
            'module_id' => $moduleId,
            'request_id' => (string) $context->correlationId,
            'error_id' => $errorId,
            'exception' => $throwable::class,
            'message' => $throwable->getMessage(),
        ]);

        return ModuleExecutionResult::failure($moduleId, $errorId);
    }
}
