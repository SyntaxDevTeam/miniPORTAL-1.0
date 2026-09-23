<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Audit;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Audit\Model\AuditResult;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\InMemoryAuditSink;
use SyntaxDevTeam\MiniPortal\Library\Audit\Provider\ScopedAuditTrail;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\FrozenClock;

final class AuditTrailTest extends TestCase
{
    public function testRecordsCompleteScopedAuditEvent(): void
    {
        $sink = new InMemoryAuditSink();
        $time = new DateTimeImmutable('2026-01-02T03:04:05+00:00');
        $trail = new ScopedAuditTrail('syntax.files', $sink, new FrozenClock($time));

        $event = $trail->record(
            'user:42',
            'filesystem.write',
            '/server.properties',
            AuditResult::Succeeded,
            'request-1234',
            ['scope_id' => 'survival', 'bytes' => 128],
        );

        self::assertSame('syntax.files', $event->packageId);
        self::assertSame($time, $event->occurredAt);
        self::assertSame([$event], $sink->events());
    }

    public function testRejectsSensitiveContextFieldsAtAnyDepth(): void
    {
        $trail = new ScopedAuditTrail(
            'fixture',
            new InMemoryAuditSink(),
            new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        );

        $this->expectException(\InvalidArgumentException::class);
        $trail->record(
            'system',
            'config.update',
            'database',
            AuditResult::Succeeded,
            'request-1234',
            ['database' => ['password' => 'must-not-be-recorded']],
        );
    }

    public function testRejectsExecutableContextValues(): void
    {
        $trail = new ScopedAuditTrail(
            'fixture',
            new InMemoryAuditSink(),
            new FrozenClock(new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
        );

        $this->expectException(\InvalidArgumentException::class);
        $trail->record(
            'system',
            'job.run',
            'job:1',
            AuditResult::Failed,
            'request-1234',
            ['callback' => static fn (): bool => true],
        );
    }
}
