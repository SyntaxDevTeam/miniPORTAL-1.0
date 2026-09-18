<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use SyntaxDevTeam\MiniPortal\Core\Contract\Event\VersionedEvent;
use SyntaxDevTeam\MiniPortal\Core\Event\EventContractId;
use SyntaxDevTeam\MiniPortal\Core\Event\EventDispatcher;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FixtureEvent;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FixtureEventV2;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;

final class EventDispatcherTest extends TestCase
{
    public function testListenerFailureDoesNotPreventRemainingListeners(): void
    {
        $logger = new InMemoryLogger();
        $dispatcher = new EventDispatcher($logger);
        $delivered = 0;

        $dispatcher->subscribe(FixtureEvent::class, static function (VersionedEvent $_): void {
            throw new RuntimeException('listener failed');
        });
        $dispatcher->subscribe(FixtureEvent::class, static function (VersionedEvent $_) use (&$delivered): void {
            $delivered++;
        });

        $report = $dispatcher->dispatch(new FixtureEvent('value'));

        self::assertFalse($report->isSuccessful());
        self::assertSame(1, $report->delivered);
        self::assertSame(1, $delivered);
        self::assertCount(1, $report->failures);
        self::assertSame('fixture.changed@1', $report->failures[0]->eventContract);
        self::assertCount(1, $logger->records);
    }

    public function testDifferentContractVersionsDoNotShareListeners(): void
    {
        $dispatcher = new EventDispatcher(new InMemoryLogger());
        $calls = 0;

        $dispatcher->subscribe(FixtureEvent::class, static function (VersionedEvent $_) use (&$calls): void {
            $calls++;
        });

        $report = $dispatcher->dispatch(new FixtureEventV2('value', 'test'));

        self::assertTrue($report->isSuccessful());
        self::assertSame(0, $report->delivered);
        self::assertSame(0, $calls);
        self::assertSame('fixture.changed@2', EventContractId::fromEventClass(FixtureEventV2::class)->key());
    }
}
