<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Core;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\Module;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleRegistration;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleServices;
use SyntaxDevTeam\MiniPortal\Core\Contract\Module\ModuleContext;
use SyntaxDevTeam\MiniPortal\Core\Http\Request;
use SyntaxDevTeam\MiniPortal\Core\Module\ActiveModuleLoader;
use SyntaxDevTeam\MiniPortal\Core\Module\ActiveModuleMount;
use SyntaxDevTeam\MiniPortal\Core\Package\Lifecycle\PackageState;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\InMemoryPackageRegistry;
use SyntaxDevTeam\MiniPortal\Core\Package\Registry\PackageRelease;
use SyntaxDevTeam\MiniPortal\Core\Routing\Router;
use SyntaxDevTeam\MiniPortal\Core\Support\CorrelationId;
use SyntaxDevTeam\MiniPortal\Tests\Fixtures\InMemoryLogger;
use SyntaxDevTeam\MiniPortal\UI\Theme\Base\BaseTheme;
use SyntaxDevTeam\MiniPortal\UI\Theme\ThemeResolver;

final class ActiveModuleLoaderTest extends TestCase
{
    public function testFactoryCreatesSystemModuleFromPublicServices(): void
    {
        $registry = new InMemoryPackageRegistry();
        $path = dirname(__DIR__, 2) . '/modules/SystemThemes';
        $manifest = (new ManifestParser())->parseFile($path . '/manifest.json');
        $registry->add(new PackageRelease($manifest, $path, PackageState::Active));
        $registry->setActive('system.themes', '1.0.0');
        $router = new Router();
        $logger = new InMemoryLogger();
        $themes = new ThemeResolver(new BaseTheme(), '1.0.0');
        $result = (new ActiveModuleLoader($registry, new ActiveModuleMount($registry, $router, $logger),
            $logger, [], new ModuleServices($themes, null, null)))
            ->mountAll(new ModuleContext(CorrelationId::fromString('request-12345678')));
        self::assertTrue($result['system.themes']->successful);
        self::assertSame(403, $router->handle(new Request('GET', '/modules/system.themes'))->status);
    }

    public function testMountsDependenciesBeforeDependents(): void
    {
        $registry = new InMemoryPackageRegistry();
        $parser = new ManifestParser();
        foreach (['fixture.a' => ['fixture.z' => '^1.0'], 'fixture.z' => []] as $id => $requires) {
            $manifest = $parser->parse(json_encode([
                'schema' => 1, 'id' => $id, 'name' => $id, 'version' => '1.0.0',
                'type' => 'module', 'requires' => ['core' => '^1.0', 'modules' => $requires],
                'entrypoint' => 'Fixture\\Module',
            ], JSON_THROW_ON_ERROR));
            $registry->add(new PackageRelease($manifest, '/fixture', PackageState::Active));
            $registry->setActive($id, '1.0.0');
        }
        $events = new \ArrayObject();
        $factory = static fn (string $id): Module => new class($id, $events) implements Module {
            /** @param \ArrayObject<int, string> $events */
            public function __construct(private string $id, private \ArrayObject $events)
            {
            }
            public function register(ModuleRegistration $registration): void
            {
            }
            public function boot(ModuleContext $context): void
            {
                $this->events->append($this->id);
            }
        };
        $router = new Router();
        $logger = new InMemoryLogger();
        (new ActiveModuleLoader($registry, new ActiveModuleMount($registry, $router, $logger), $logger))
            ->mountAll(new ModuleContext(CorrelationId::fromString('request-12345678')),
                ['fixture.a' => $factory('fixture.a'), 'fixture.z' => $factory('fixture.z')]);
        self::assertSame(['fixture.z', 'fixture.a'], $events->getArrayCopy());
    }

    public function testMountsReleaseClassAndIsolatesInvalidEntrypoint(): void
    {
        $registry = new InMemoryPackageRegistry();
        $parser = new ManifestParser();
        foreach ([
            ['fixture.status', 'SyntaxDevTeam\\MiniPortal\\Module\\FixtureTest\\StatusModule',
                dirname(__DIR__) . '/Fixtures/PackageRelease'],
            ['fixture.broken', 'SyntaxDevTeam\\MiniPortal\\Module\\Missing\\MissingModule',
                dirname(__DIR__) . '/Fixtures/PackageRelease'],
        ] as [$id, $entrypoint, $path]) {
            $manifest = $parser->parse(json_encode([
                'schema' => 1, 'id' => $id, 'name' => $id, 'version' => '1.0.0',
                'type' => 'module', 'requires' => ['core' => '^1.0'], 'entrypoint' => $entrypoint,
            ], JSON_THROW_ON_ERROR));
            $registry->add(new PackageRelease($manifest, $path, PackageState::Active));
            $registry->setActive($id, '1.0.0');
        }
        $router = new Router();
        $logger = new InMemoryLogger();
        $results = (new ActiveModuleLoader($registry, new ActiveModuleMount($registry, $router, $logger), $logger))
            ->mountAll(new ModuleContext(CorrelationId::fromString('request-12345678')));

        self::assertFalse($results['fixture.broken']->successful);
        self::assertTrue($results['fixture.status']->successful);
        self::assertSame('fixture ready', $router->handle(new Request('GET', '/modules/fixture.status/status'))->body);
        self::assertCount(1, $logger->records);
    }
}
