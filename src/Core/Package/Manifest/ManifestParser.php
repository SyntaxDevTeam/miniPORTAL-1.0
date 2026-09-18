<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Manifest;

use JsonException;

final class ManifestParser
{
    public function parseFile(string $path): PackageManifest
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw new ManifestValidationException([
                new ManifestValidationError('$', sprintf('Manifest file is not readable: %s', $path)),
            ]);
        }

        return $this->parse($contents);
    }

    public function parse(string $json): PackageManifest
    {
        try {
            $data = json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new ManifestValidationException([
                new ManifestValidationError('$', 'Invalid JSON: ' . $exception->getMessage()),
            ]);
        }

        if (!is_array($data) || (array_is_list($data) && $data !== [])) {
            throw new ManifestValidationException([
                new ManifestValidationError('$', 'Manifest root must be an object.'),
            ]);
        }

        /** @var array<string, mixed> $data */
        /** @var list<ManifestValidationError> $errors */
        $errors = [];

        $schema = $this->integer($data, 'schema', '$.schema', $errors);
        if ($schema !== null && $schema !== 1) {
            $errors[] = new ManifestValidationError('$.schema', 'Only manifest schema version 1 is supported.');
        }

        $id = $this->string($data, 'id', '$.id', $errors);
        if ($id !== null && preg_match('/^[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*$/', $id) !== 1) {
            $errors[] = new ManifestValidationError('$.id', 'Package ID must use lowercase letters, digits, dashes and optional dot-separated namespaces.');
        }

        $name = $this->string($data, 'name', '$.name', $errors);
        $version = $this->string($data, 'version', '$.version', $errors);
        if ($version !== null && preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            $errors[] = new ManifestValidationError('$.version', 'Package version must be valid SemVer.');
        }

        $typeValue = $this->string($data, 'type', '$.type', $errors);
        $type = $typeValue !== null ? PackageType::tryFrom($typeValue) : null;
        if ($typeValue !== null && $type === null) {
            $errors[] = new ManifestValidationError('$.type', 'Package type must be one of: module, library, provider, theme.');
        }

        $requires = $data['requires'] ?? [];
        if (!is_array($requires) || (array_is_list($requires) && $requires !== [])) {
            $errors[] = new ManifestValidationError('$.requires', 'requires must be an object.');
            $requires = [];
        }
        /** @var array<string, mixed> $requires */

        $coreConstraint = $this->optionalString($requires, 'core', '$.requires.core', $errors) ?? '*';
        $capabilities = $this->stringMap($requires, 'capabilities', '$.requires.capabilities', $errors);
        $modules = $this->stringMap($requires, 'modules', '$.requires.modules', $errors);
        $entrypoint = $this->optionalString($data, 'entrypoint', '$.entrypoint', $errors);

        if (($type === PackageType::Module || $type === PackageType::Provider) && $entrypoint === null) {
            $errors[] = new ManifestValidationError('$.entrypoint', 'Module and provider packages require an entrypoint.');
        }

        if ($errors !== []) {
            throw new ManifestValidationException($errors);
        }

        return new PackageManifest(
            $schema ?? 1,
            $id ?? '',
            $name ?? '',
            $version ?? '',
            $type ?? PackageType::Library,
            $coreConstraint,
            $capabilities,
            $modules,
            $entrypoint,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param list<ManifestValidationError> $errors
     */
    private function string(array $data, string $key, string $path, array &$errors): ?string
    {
        if (!array_key_exists($key, $data) || !is_string($data[$key]) || trim($data[$key]) === '') {
            $errors[] = new ManifestValidationError($path, 'Expected a non-empty string.');
            return null;
        }
        return trim($data[$key]);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<ManifestValidationError> $errors
     */
    private function optionalString(array $data, string $key, string $path, array &$errors): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        if (!is_string($data[$key]) || trim($data[$key]) === '') {
            $errors[] = new ManifestValidationError($path, 'Expected a non-empty string when present.');
            return null;
        }
        return trim($data[$key]);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<ManifestValidationError> $errors
     */
    private function integer(array $data, string $key, string $path, array &$errors): ?int
    {
        if (!array_key_exists($key, $data) || !is_int($data[$key])) {
            $errors[] = new ManifestValidationError($path, 'Expected an integer.');
            return null;
        }
        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<ManifestValidationError> $errors
     * @return array<string, string>
     */
    private function stringMap(array $data, string $key, string $path, array &$errors): array
    {
        if (!array_key_exists($key, $data)) {
            return [];
        }
        if (!is_array($data[$key])) {
            $errors[] = new ManifestValidationError($path, 'Expected an object of string constraints.');
            return [];
        }

        $result = [];
        foreach ($data[$key] as $name => $constraint) {
            if (!is_string($name) || !is_string($constraint) || trim($constraint) === '') {
                $errors[] = new ManifestValidationError($path, 'Every dependency key and constraint must be a non-empty string.');
                continue;
            }
            $result[$name] = trim($constraint);
        }
        return $result;
    }
}
