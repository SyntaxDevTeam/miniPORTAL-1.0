<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use stdClass;
use SyntaxDevTeam\MiniPortal\Core\Capability\CapabilityRegistry;
use SyntaxDevTeam\MiniPortal\Core\Capability\RegisteredCapability;

final class CapabilityRegistryTest extends TestCase
{
    public function testRegistersRuntimeCapabilityMetadataAndService(): void
    {
        $registry = new CapabilityRegistry();
        $service = new stdClass();

        $registry->register(new RegisteredCapability(
            'filesystem',
            '1.2.0',
            'local-filesystem',
            $service,
        ));

        $registered = $registry->find('filesystem');

        self::assertNotNull($registered);
        self::assertSame($service, $registered->service);
        self::assertSame(['filesystem' => '1.2.0'], $registry->versions());
        self::assertSame(['filesystem' => 'local-filesystem'], $registry->providerMap());
    }

    public function testRejectsSecondRuntimeProviderForSameCapability(): void
    {
        $registry = new CapabilityRegistry();
        $registry->register(new RegisteredCapability(
            'filesystem',
            '1.2.0',
            'local-filesystem',
            new stdClass(),
        ));

        $this->expectException(\LogicException::class);

        $registry->register(new RegisteredCapability(
            'filesystem',
            '1.3.0',
            'remote-filesystem',
            new stdClass(),
        ));
    }

    public function testRegistryExportsCapabilitiesInDeterministicOrder(): void
    {
        $registry = new CapabilityRegistry();

        $registry->register(new RegisteredCapability('realtime', '1.0.0', '@core', new stdClass()));
        $registry->register(new RegisteredCapability('cache', '1.1.0', '@core', new stdClass()));

        self::assertSame(['cache', 'realtime'], array_map(
            static fn (RegisteredCapability $capability): string => $capability->name,
            $registry->all(),
        ));
    }
}
