<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestValidationException;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;

final class ManifestParserTest extends TestCase
{
    public function testParsesCanonicalModuleManifest(): void
    {
        $manifest = (new ManifestParser())->parse((string) json_encode([
            'schema' => 1,
            'id' => 'fixture-good',
            'name' => 'Fixture Good',
            'version' => '1.0.0',
            'type' => 'module',
            'requires' => [
                'core' => '^1.0',
                'capabilities' => ['filesystem' => '^1.0'],
            ],
            'entrypoint' => 'Fixture\\GoodModule',
        ], JSON_THROW_ON_ERROR));

        self::assertSame('fixture-good', $manifest->id);
        self::assertSame(PackageType::Module, $manifest->type);
        self::assertSame(['filesystem' => '^1.0'], $manifest->capabilities);
    }

    public function testRejectsInvalidManifestWithoutFatalError(): void
    {
        try {
            (new ManifestParser())->parse('{"schema":1,"id":"BAD ID","name":"Broken","version":"wat","type":"module"}');
            self::fail('Expected validation exception.');
        } catch (ManifestValidationException $exception) {
            self::assertGreaterThanOrEqual(3, count($exception->errors));
        }
    }
}
