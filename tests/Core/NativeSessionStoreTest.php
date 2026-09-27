<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\NativeSessionStore;

final class NativeSessionStoreTest extends TestCase
{
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testCookieSupportsSecureTopLevelOAuthCallback(): void
    {
        self::assertNotFalse(ini_set('session.save_path', sys_get_temp_dir()));
        new NativeSessionStore(true);

        $parameters = session_get_cookie_params();
        self::assertTrue($parameters['secure']);
        self::assertTrue($parameters['httponly']);
        self::assertSame('Lax', $parameters['samesite']);
        self::assertSame('/', $parameters['path']);
        self::assertSame('MINIPORTALSESSID', session_name());
    }
}
