<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use stdClass;
use SyntaxDevTeam\MiniPortal\Core\DependencyInjection\CircularDependency;
use SyntaxDevTeam\MiniPortal\Core\DependencyInjection\ServiceContainer;

final class ServiceContainerTest extends TestCase
{
    public function testFactoryIsLazyAndServiceIsShared(): void
    {
        $container = new ServiceContainer();
        $calls = 0;

        $container->set('service', static function () use (&$calls): object {
            $calls++;
            return new stdClass();
        });

        self::assertSame(0, $calls);

        $first = $container->get('service');
        $second = $container->get('service');

        self::assertSame(1, $calls);
        self::assertSame($first, $second);
    }

    public function testCircularDependencyIsRejected(): void
    {
        $container = new ServiceContainer();

        $container->set('a', static fn (ServiceContainer $services): object => $services->get('b'));
        $container->set('b', static fn (ServiceContainer $services): object => $services->get('a'));

        $this->expectException(CircularDependency::class);

        $container->get('a');
    }
}
