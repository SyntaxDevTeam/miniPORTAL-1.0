<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Kernel;

use SyntaxDevTeam\MiniPortal\Core\Configuration\ApplicationConfig;
use SyntaxDevTeam\MiniPortal\Core\Configuration\ConfigurationLoader;
use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Core\Error\SafeErrorHandler;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final readonly class Runtime
{
    private function __construct(
        public ApplicationEnvironment $environment,
        public CorrelationId $correlationId,
        public SafeErrorHandler $errorHandler,
        public ApplicationConfig $config,
    ) {
    }

    public static function boot(?string $environment = null, ?ApplicationConfig $config = null): self
    {
        if ($config === null) {
            $config = (new ConfigurationLoader())->load(dirname(__DIR__, 3), getenv());
        }
        $appEnvironment = $environment === null
            ? $config->environment
            : ApplicationEnvironment::fromEnvironment($environment);
        if ($appEnvironment !== $config->environment) {
            $config = new ApplicationConfig($appEnvironment, $config->database, $config->authentication);
        }
        $correlationId = CorrelationId::generate();
        $errorHandler = new SafeErrorHandler($appEnvironment, $correlationId);
        $errorHandler->register();

        return new self($appEnvironment, $correlationId, $errorHandler, $config);
    }
}
