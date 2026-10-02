<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Navigation\BufferedNavigationRegistrar;
use SyntaxDevTeam\MiniPortal\Core\Navigation\NavigationCatalog;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycle;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageLifecycleManager;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;

final class NavigationCatalogTest extends TestCase
{
    public function testOnlyActiveModuleNavigationIsExposed(): void
    {
        $manifest = (new ManifestParser())->parse(json_encode([
            'schema' => 1, 'id' => 'site.pages', 'name' => 'Pages', 'version' => '1.0.0',
            'type' => 'module', 'requires' => ['core' => '^1.0'],
            'entrypoint' => 'SyntaxDevTeam\\MiniPortal\\Module\\SitePages\\SitePagesModule',
        ], JSON_THROW_ON_ERROR));
        $registry = new InMemoryPackageRegistry();
        $registry->add(new PackageRelease($manifest, '/fixture', PackageState::Active));
        $registry->setActive('site.pages', '1.0.0');
        $registrar = new BufferedNavigationRegistrar('site.pages');
        $registrar->add('pages', 'Strony');
        $registrar->add('manage', 'Zarządzaj', '/admin', 'admin');
        $catalog = new NavigationCatalog($registry);
        $catalog->registerModule('site.pages', $registrar->items());
        self::assertCount(1, $catalog->actions('public'));
        self::assertSame('/modules/site.pages', $catalog->actions('public')[0]->url);
        self::assertSame('/modules/site.pages/admin', $catalog->actions('admin')[0]->url);
        (new PackageLifecycleManager($registry, new PackageLifecycle()))
            ->transition('site.pages', '1.0.0', PackageState::Disabled);
        self::assertSame([], $catalog->actions('public'));
    }

    public function testModuleCannotPublishNavigationOutsideOwnMount(): void
    {
        $registrar = new BufferedNavigationRegistrar('site.pages');
        $this->expectException(\InvalidArgumentException::class);
        $registrar->add('escape', 'Escape', '/../admin');
    }
}
