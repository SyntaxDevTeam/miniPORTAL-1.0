<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Module;

use SyntaxDevTeam\MiniPortal\Core\Contract\Logging\Logger;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class ModuleDispatcher
{
    public function __construct(private Logger $logger)
    {
    }

    public function boot(string $moduleId, Module $module, ModuleContext $context): ModuleExecutionResult
    {
        try {
            $module->boot($context);
            return ModuleExecutionResult::success($moduleId, ModuleExecutionPhase::Boot);
        } catch (\Throwable $throwable) {
            $errorId = (string) CorrelationId::generate();
            $this->logger->error('Module boot failed.', [
                'module_id' => $moduleId,
                'phase' => ModuleExecutionPhase::Boot->value,
                'request_id' => (string) $context->correlationId,
                'error_id' => $errorId,
                'exception' => $throwable::class,
                'message' => $throwable->getMessage(),
            ]);

            return ModuleExecutionResult::failure(
                $moduleId,
                ModuleExecutionPhase::Boot,
                $errorId,
            );
        }
    }
}
