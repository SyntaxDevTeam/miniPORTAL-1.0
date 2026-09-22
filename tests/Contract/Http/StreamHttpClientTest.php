<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Http;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Http\Exception\HttpTransportFailed;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpPolicy;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpRequest;
use SyntaxDevTeam\MiniPortal\Library\Http\Model\HttpResponse;
use SyntaxDevTeam\MiniPortal\Library\Http\Provider\StreamHttpClient;

final class StreamHttpClientTest extends TestCase
{
    public function testRetryableGetRecoversAndReportsAttempts(): void
    {
        $attempts = 0;
        $diagnostics = [];
        $client = new StreamHttpClient(
            new HttpPolicy(1.0, 3),
            static function () use (&$attempts): HttpResponse {
                $attempts++;
                return new HttpResponse($attempts === 1 ? 503 : 200, [], 'ok');
            },
            static function (string $method, int $attempt, ?int $status, bool $failed) use (&$diagnostics): void {
                $diagnostics[] = [$method, $attempt, $status, $failed];
            },
        );

        self::assertSame(200, $client->send(new HttpRequest('GET', 'https://example.com/'))->status);
        self::assertSame(2, $attempts);
        self::assertSame([['GET', 1, 503, false], ['GET', 2, 200, false]], $diagnostics);
    }

    public function testPostIsNeverAutomaticallyRetried(): void
    {
        $attempts = 0;
        $client = new StreamHttpClient(new HttpPolicy(1.0, 3), static function () use (&$attempts): HttpResponse {
            $attempts++;
            return new HttpResponse(503, [], 'busy');
        });

        self::assertSame(503, $client->send(new HttpRequest('POST', 'https://example.com/'))->status);
        self::assertSame(1, $attempts);
    }

    public function testTransportFailureUsesStableExceptionAfterBoundedRetries(): void
    {
        $attempts = 0;
        $client = new StreamHttpClient(new HttpPolicy(1.0, 2), static function () use (&$attempts): never {
            $attempts++;
            throw new HttpTransportFailed('unavailable');
        });

        try {
            $client->send(new HttpRequest('GET', 'https://example.com/'));
            self::fail('Transport failure should propagate.');
        } catch (HttpTransportFailed) {
            self::assertSame(2, $attempts);
        }
    }

    public function testRequestRejectsHeaderInjectionAndUrlCredentials(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpRequest('GET', 'https://user:secret@example.com/');
    }

    public function testRequestRejectsHeaderNewline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new HttpRequest('GET', 'https://example.com/', ['X-Test' => "safe\r\nInjected: yes"]);
    }
}
