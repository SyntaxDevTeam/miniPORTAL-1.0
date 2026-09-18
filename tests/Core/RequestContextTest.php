<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use stdClass;
use SyntaxDevTeam\MiniPortal\Core\Capability\CapabilityRegistry;
use SyntaxDevTeam\MiniPortal\Core\Capability\RegisteredCapability;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContext;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContextFactory;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;

final class RequestContextTest extends TestCase
{
    public function testFactoryBuildsImmutableSecurityAndCapabilitySnapshot(): void
    {
        $capabilities = new CapabilityRegistry();
        $capabilities->register(new RegisteredCapability(
            'filesystem',
            '1.2.0',
            'local-filesystem',
            new stdClass(),
        ));

        $correlationId = CorrelationId::fromString('request-1234');
        $context = (new RequestContextFactory($correlationId, $capabilities))->create(
            principalId: 'user-42',
            locale: 'pl_PL',
            timezone: 'Europe/Warsaw',
            clientHints: ['viewport' => 'desktop'],
            permissions: ['core.dashboard.view'],
            csrfToken: 'csrf-token',
        );

        self::assertSame($correlationId, $context->correlationId);
        self::assertTrue($context->isAuthenticated());
        self::assertSame('pl-PL', $context->locale);
        self::assertTrue($context->hasPermission('core.dashboard.view'));
        self::assertFalse($context->hasPermission('core.admin'));
        self::assertSame('local-filesystem', $context->capabilityProvider('filesystem'));
    }

    public function testRequestPreservesContextWhenRouteAttributesAreAdded(): void
    {
        $context = new RequestContext(CorrelationId::fromString('request-5678'));

        $request = (new Request('GET', '/modules/example/item'))
            ->withContext($context)
            ->withAttributes(['item' => '42']);

        self::assertSame($context, $request->context);
        self::assertSame('42', $request->attribute('item'));
    }

    public function testAnonymousContextHasSafeDefaults(): void
    {
        $context = new RequestContext(CorrelationId::fromString('request-9012'));

        self::assertFalse($context->isAuthenticated());
        self::assertSame('en', $context->locale);
        self::assertSame('UTC', $context->timezone);
        self::assertNull($context->csrfToken);
        self::assertSame([], $context->permissions);
    }

    public function testInvalidTimezoneIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new RequestContext(
            CorrelationId::fromString('request-3456'),
            timezone: 'Not/A-Timezone',
        );
    }
}
