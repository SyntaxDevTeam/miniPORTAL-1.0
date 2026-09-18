<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Contract\Storage;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\ProviderUnavailable;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoConnectionConfig;
use SyntaxDevTeam\MiniPortal\Library\Storage\Provider\Pdo\PdoDatabaseFactory;

final class PdoDatabaseFactoryTest extends TestCase
{
    public function testConnectionFailureUsesStableProviderException(): void
    {
        $this->expectException(ProviderUnavailable::class);

        (new PdoDatabaseFactory())->connect(
            new PdoConnectionConfig('miniportal-unsupported-driver:fixture'),
        );
    }
}
