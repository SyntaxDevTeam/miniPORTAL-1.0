<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Module\ModuleDispatcher;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\ThrowingModule;

final class ModuleDispatcherTest extends TestCase
{
    public function testModuleFailureRemainsInsideDispatcherBoundary(): void
    {
        $logger = new InMemoryLogger();
        $dispatcher = new ModuleDispatcher($logger);
        $context = new ModuleContext(CorrelationId::fromString('request-12345678'));

        $result = $dispatcher->boot('fixture-runtime-error', new ThrowingModule(), $context);

        self::assertFalse($result->successful);
        self::assertNotNull($result->errorId);
        self::assertCount(1, $logger->records);
        self::assertSame('fixture-runtime-error', $logger->records[0]['context']['module_id']);
        self::assertSame('request-12345678', $logger->records[0]['context']['request_id']);
    }
}
