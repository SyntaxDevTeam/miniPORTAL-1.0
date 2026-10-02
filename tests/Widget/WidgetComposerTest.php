<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Widget;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Http\RequestContext;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageManifest;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Core\Widget\InMemoryWidgetPlacementRepository;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetCatalog;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetComposer;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetInstance;
use SyntaxDevTeam\MiniPortal\Core\Widget\WidgetProvider;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\UI\Component\Text;
use SyntaxDevTeam\MiniPortal\UI\Model\PageRegion;
use SyntaxDevTeam\MiniPortal\UI\PageDefinition;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\Plasma\PlasmaTheme;

final class WidgetComposerTest extends TestCase
{
    public function testActiveWidgetsRespectPositionPermissionAndLocalFailureInBothThemes(): void
    {
        $placements = new InMemoryWidgetPlacementRepository();
        $placements->save(new WidgetInstance('widget.good', 'home', 'hero.inline', 'fixture.widgets', 'headline', 20));
        $placements->save(new WidgetInstance('widget.private', 'home', 'hero.inline', 'fixture.widgets', 'private', 10,
            requiredPermission: 'dashboard.view'));
        $placements->save(new WidgetInstance('widget.bad', 'home', 'hero.inline', 'fixture.widgets', 'broken', 30));
        $packages = new InMemoryPackageRegistry();
        $manifest = new PackageManifest(1, 'fixture.widgets', 'Fixture Widgets', '1.0.0',
            PackageType::Module, '^1.0', [], [], [], 'Fixture\\Widgets');
        $packages->add(new PackageRelease($manifest, '/fixture/widgets', PackageState::Active));
        $packages->setActive('fixture.widgets', '1.0.0');
        $catalog = new WidgetCatalog();
        $catalog->registerModule('fixture.widgets', [
            'headline' => new class implements WidgetProvider {
                public function render(WidgetInstance $instance, ?RequestContext $context): array
                {
                    return [new Text('Public widget')];
                }
            },
            'private' => new class implements WidgetProvider {
                public function render(WidgetInstance $instance, ?RequestContext $context): array
                {
                    return [new Text('Private widget')];
                }
            },
            'broken' => new class implements WidgetProvider {
                public function render(WidgetInstance $instance, ?RequestContext $context): array
                {
                    throw new \RuntimeException('Provider failed.');
                }
            },
        ]);
        $logger = new InMemoryLogger();
        $composer = new WidgetComposer($placements, $catalog, $packages, $logger);
        $publicSlot = $composer->slot('home', 'hero.inline', null);
        $page = new PageDefinition('home', 'Home', 'public', [PageRegion::CONTENT => [$publicSlot]]);
        foreach ([new BaseTheme(), new PlasmaTheme()] as $theme) {
            $html = $theme->render($page);
            self::assertStringContainsString('Public widget', $html);
            self::assertStringNotContainsString('Private widget', $html);
            self::assertStringContainsString('Widget jest niedostępny', $html);
        }
        self::assertCount(1, $logger->records);
        $context = new RequestContext(CorrelationId::generate(), permissions: ['dashboard.view']);
        $privatePage = new PageDefinition('home', 'Home', 'public', [PageRegion::CONTENT => [
            $composer->slot('home', 'hero.inline', $context),
        ]]);
        $html = (new BaseTheme())->render($privatePage);
        self::assertLessThan(strpos($html, 'Public widget'), strpos($html, 'Private widget'));
        $packages->clearActive('fixture.widgets');
        self::assertSame([], $composer->slot('home', 'hero.inline', $context)->children());
        self::assertCount(2, $logger->records);
    }
}
