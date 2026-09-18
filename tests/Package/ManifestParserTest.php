<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Tests\Package;

use JsonException;
use PHPUnit\Framework\TestCase;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestValidationException;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\PackageType;

final class ManifestParserTest extends TestCase
{
    public function testParsesCanonicalProviderManifest(): void
    {
        $manifest = (new ManifestParser())->parse((string) json_encode([
            'schema' => 1,
            'id' => 'filesystem.local',
            'name' => 'Local Filesystem Provider',
            'version' => '1.0.0',
            'type' => 'provider',
            'requires' => [
                'core' => '^1.0',
                'capabilities' => ['logging' => '^1.0'],
            ],
            'provides' => [
                'capabilities' => ['filesystem' => '1.0.0'],
            ],
            'entrypoint' => 'Fixture\\LocalFilesystemProvider',
        ], JSON_THROW_ON_ERROR));

        self::assertSame('filesystem.local', $manifest->id);
        self::assertSame(PackageType::Provider, $manifest->type);
        self::assertSame(['logging' => '^1.0'], $manifest->requiredCapabilities);
        self::assertSame(['filesystem' => '1.0.0'], $manifest->providedCapabilities);
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

    public function testCanonicalSchemaDocumentIsValidJson(): void
    {
        $path = dirname(__DIR__, 2) . '/resources/schema/package-manifest-v1.schema.json';
        $contents = file_get_contents($path);

        self::assertNotFalse($contents);

        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            self::fail('Canonical manifest schema must be valid JSON: ' . $exception->getMessage());
        }

        self::assertIsArray($decoded);
        self::assertSame('object', $decoded['type'] ?? null);
    }
}
