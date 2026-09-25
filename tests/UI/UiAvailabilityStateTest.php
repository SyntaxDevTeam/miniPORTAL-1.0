<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\UI\Component\Card;
use SyntaxDevTeam\MiniPortal\UI\Component\DegradedState;
use SyntaxDevTeam\MiniPortal\UI\Component\PermissionDeniedState;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;

final class UiAvailabilityStateTest extends TestCase
{
    public function testPermissionDeniedStateEscapesVisibleContext(): void
    {
        $html = (new BaseTheme())->renderers()->render(new PermissionDeniedState(
            'Brak <dostępu>',
            'Skontaktuj się z administratorem & spróbuj ponownie.',
            'module.files.read',
        ));

        self::assertStringContainsString('role="alert"', $html);
        self::assertStringContainsString('Brak &lt;dostępu&gt;', $html);
        self::assertStringContainsString('administratorem &amp; spróbuj', $html);
        self::assertStringContainsString('<code>module.files.read</code>', $html);
    }

    public function testPermissionDeniedStateRejectsUnsafePermissionIdentifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PermissionDeniedState(requiredPermission: '<script>');
    }

    public function testDegradedStateKeepsAvailableContentRenderable(): void
    {
        $state = new DegradedState(
            'Tryb ograniczony',
            'Część danych jest niedostępna.',
            [new Card([new Text('Dane z cache')], 'Dostępne')],
        );

        $html = (new BaseTheme())->renderers()->render($state);

        self::assertStringContainsString('role="status"', $html);
        self::assertStringContainsString('mp-degraded-state__available', $html);
        self::assertStringContainsString('Dane z cache', $html);
    }
}
