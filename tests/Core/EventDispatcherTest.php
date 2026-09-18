<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SyntaxDevTeam\MiniPortal\Core\Event\EventDispatcher;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;

final class EventDispatcherTest extends TestCase
{
    public function testListenerFailureDoesNotPreventRemainingListeners(): void
    {
        $logger = new InMemoryLogger();
        $dispatcher = new EventDispatcher($logger);
        $delivered = 0;

        $dispatcher->subscribe(stdClass::class, static function (object $_): void {
            throw new RuntimeException('listener failed');
        });
        $dispatcher->subscribe(stdClass::class, static function (object $_) use (&$delivered): void {
            $delivered++;
        });

        $report = $dispatcher->dispatch(new \stdClass());

        self::assertFalse($report->isSuccessful());
        self::assertSame(1, $report->delivered);
        self::assertSame(1, $delivered);
        self::assertCount(1, $report->failures);
        self::assertCount(1, $logger->records);
    }
}
