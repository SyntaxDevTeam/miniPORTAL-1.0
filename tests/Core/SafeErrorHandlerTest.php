<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SyntaxDevTeam\MiniPortal\Core\Environment\ApplicationEnvironment;
use SyntaxDevTeam\MiniPortal\Core\Error\SafeErrorHandler;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final class SafeErrorHandlerTest extends TestCase
{
    public function testProductionResponseDoesNotLeakExceptionDetails(): void
    {
        $handler = new SafeErrorHandler(
            ApplicationEnvironment::Production,
            CorrelationId::fromString('request-12345678'),
        );

        $response = $handler->response(new RuntimeException('database-password=secret'));

        self::assertSame(500, $response->status);
        self::assertStringContainsString('request-12345678', $response->body);
        self::assertStringNotContainsString('database-password=secret', $response->body);
    }

    public function testDevelopmentResponseContainsUsefulExceptionDetails(): void
    {
        $handler = new SafeErrorHandler(
            ApplicationEnvironment::Development,
            CorrelationId::fromString('request-12345678'),
        );

        $response = $handler->response(new RuntimeException('development detail'));

        self::assertStringContainsString('development detail', $response->body);
    }
}
