<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\UI;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\UI\FixtureTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

final class ThemeResolverTest extends TestCase
{
    public function testResolvesCompatibleThemeSupportingRequestedLayout(): void
    {
        $resolver = new ThemeResolver(new BaseTheme(), '1.0.0');
        $resolver->register(new FixtureTheme('custom', '^1.0', ['dashboard']));

        $result = $resolver->resolve('custom', 'dashboard');

        self::assertSame('custom', $result->theme->id());
        self::assertFalse($result->fallbackUsed);
        self::assertNull($result->fallbackReason);
        self::assertSame(['base', 'custom'], $resolver->registeredThemeIds());
    }

    public function testFallsBackForMissingIncompatibleOrUnsupportedTheme(): void
    {
        $resolver = new ThemeResolver(new BaseTheme(), '1.0.0');
        $resolver->register(new FixtureTheme('future', '^2.0', ['dashboard']));
        $resolver->register(new FixtureTheme('limited', '^1.0', ['public']));

        foreach ([['missing', 'dashboard'], ['future', 'dashboard'], ['limited', 'dashboard']] as [$theme, $layout]) {
            $result = $resolver->resolve($theme, $layout);
            self::assertSame('base', $result->theme->id());
            self::assertTrue($result->fallbackUsed);
            self::assertNotNull($result->fallbackReason);
        }
    }

    public function testRejectsDuplicateThemeRegistration(): void
    {
        $resolver = new ThemeResolver(new BaseTheme(), '1.0.0');
        $resolver->register(new FixtureTheme('custom', '^1.0', ['public']));

        $this->expectException(\LogicException::class);
        $resolver->register(new FixtureTheme('custom', '^1.0', ['dashboard']));
    }
}
