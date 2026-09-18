<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Error;

use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Core\Http\Response;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final class SafeErrorHandler
{
    public function __construct(
        private readonly ApplicationEnvironment $environment,
        private readonly CorrelationId $correlationId,
    ) {
    }

    public function register(): void
    {
        set_exception_handler(function (\Throwable $throwable): void {
            $this->report($throwable);
            $this->response($throwable)->send();
        });
    }

    public function response(\Throwable $throwable): Response
    {
        $errorId = htmlspecialchars((string) $this->correlationId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $details = '';

        if (!$this->environment->isProduction()) {
            $details = sprintf(
                '<pre>%s</pre>',
                htmlspecialchars($throwable::class . ': ' . $throwable->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            );
        }

        return Response::html(
            '<!doctype html><html lang="en"><meta charset="utf-8"><title>miniPORTAL error</title>'
            . '<body><main><h1>Something went wrong</h1>'
            . '<p>The request could not be completed. Error ID: <code>' . $errorId . '</code></p>'
            . $details . '</main></body></html>',
            500,
        );
    }

    private function report(\Throwable $throwable): void
    {
        error_log(sprintf(
            '[miniPORTAL][%s] %s: %s in %s:%d',
            (string) $this->correlationId,
            $throwable::class,
            $throwable->getMessage(),
            $throwable->getFile(),
            $throwable->getLine(),
        ));
    }
}
