<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Diagnostics\Doctor;

final class DoctorTest extends TestCase
{
    public function testProjectSchemaAndRuntimeChecksAreReported(): void
    {
        $doctor = new Doctor(dirname(__DIR__, 2));
        $checks = [];

        foreach ($doctor->checks() as $check) {
            $checks[$check->name] = $check;
        }

        self::assertArrayHasKey('php', $checks);
        self::assertArrayHasKey('manifest_schema', $checks);
        self::assertArrayHasKey('vendor', $checks);
        self::assertTrue($checks['manifest_schema']->ok);
        self::assertTrue($checks['vendor']->ok);
    }
}
