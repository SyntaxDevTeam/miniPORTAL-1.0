<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Diagnostics;

use JsonException;

final readonly class Doctor
{
    public function __construct(private string $projectRoot)
    {
    }

    /** @return list<DoctorCheck> */
    public function checks(): array
    {
        return [
            new DoctorCheck(
                'php',
                version_compare(PHP_VERSION, '8.5.0', '>='),
                sprintf('PHP %s; required >= 8.5.0', PHP_VERSION),
            ),
            new DoctorCheck(
                'json',
                extension_loaded('json'),
                extension_loaded('json') ? 'ext-json loaded' : 'ext-json missing',
            ),
            new DoctorCheck(
                'random',
                function_exists('random_bytes'),
                function_exists('random_bytes') ? 'CSPRNG available' : 'random_bytes unavailable',
            ),
            $this->manifestSchemaCheck(),
            new DoctorCheck(
                'vendor',
                is_file($this->projectRoot . '/vendor/autoload.php'),
                is_file($this->projectRoot . '/vendor/autoload.php')
                    ? 'Composer autoloader present'
                    : 'vendor/autoload.php missing; run composer install',
            ),
        ];
    }

    public function isHealthy(): bool
    {
        foreach ($this->checks() as $check) {
            if (!$check->ok) {
                return false;
            }
        }

        return true;
    }

    private function manifestSchemaCheck(): DoctorCheck
    {
        $path = $this->projectRoot . '/resources/schema/package-manifest-v1.schema.json';
        $contents = @file_get_contents($path);
        if ($contents === false) {
            return new DoctorCheck('manifest_schema', false, 'Canonical package manifest schema is missing or unreadable');
        }

        try {
            $decoded = json_decode($contents, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return new DoctorCheck('manifest_schema', false, 'Canonical package manifest schema is invalid JSON: ' . $exception->getMessage());
        }

        $valid = is_array($decoded) && ($decoded['type'] ?? null) === 'object';

        return new DoctorCheck(
            'manifest_schema',
            $valid,
            $valid ? 'Canonical package manifest schema is readable' : 'Canonical package manifest schema has an unexpected root',
        );
    }
}
