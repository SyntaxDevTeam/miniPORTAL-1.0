<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Kernel;

use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Core\Error\SafeErrorHandler;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class Runtime
{
    private function __construct(
        public ApplicationEnvironment $environment,
        public CorrelationId $correlationId,
        public SafeErrorHandler $errorHandler,
    ) {
    }

    public static function boot(?string $environment = null): self
    {
        $appEnvironment = ApplicationEnvironment::fromEnvironment($environment ?? getenv('MINIPORTAL_ENV') ?: null);
        $correlationId = CorrelationId::generate();
        $errorHandler = new SafeErrorHandler($appEnvironment, $correlationId);
        $errorHandler->register();

        return new self($appEnvironment, $correlationId, $errorHandler);
    }
}
