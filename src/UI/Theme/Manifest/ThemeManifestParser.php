<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\UI\Theme\Manifest;

final class ThemeManifestParser
{
    public function parseFile(string $path): ThemeManifest
    {
        $json = @file_get_contents($path);
        if ($json === false) {
            throw new ThemeManifestException([sprintf('Manifest is not readable: %s.', $path)]);
        }
        return $this->parse($json);
    }

    public function parse(string $json): ThemeManifest
    {
        try {
            $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new ThemeManifestException(['Invalid JSON: ' . $exception->getMessage()]);
        }
        if (!is_array($data) || array_is_list($data)) {
            throw new ThemeManifestException(['Manifest root must be an object.']);
        }

        /** @var array<string, mixed> $data */
        /** @var list<string> $errors */
        $errors = [];
        $schema = $this->integer($data, 'schema', $errors);
        $id = $this->string($data, 'id', $errors);
        $name = $this->string($data, 'name', $errors);
        $version = $this->string($data, 'version', $errors);
        $extends = $this->string($data, 'extends', $errors);
        $requires = $this->object($data, 'requires', $errors);
        $uiApi = $this->string($requires, 'uiApi', $errors, 'requires.uiApi');
        $layouts = $this->stringList($data, 'layouts', $errors);
        $assets = $this->stringMap($data, 'assets', $errors);
        $scheme = $this->nullableString($data, 'preferredColorScheme', $errors);

        if ($schema !== null && $schema !== 1) {
            $errors[] = 'schema: only version 1 is supported.';
        }
        if ($id !== null && preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id) !== 1) {
            $errors[] = 'id: expected a lowercase semantic identifier.';
        }
        if ($version !== null && preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/D', $version) !== 1) {
            $errors[] = 'version: expected SemVer.';
        }
        if ($layouts === []) {
            $errors[] = 'layouts: at least one layout role is required.';
        }
        foreach ($layouts as $layout) {
            if (preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $layout) !== 1) {
                $errors[] = sprintf('layouts: invalid role "%s".', $layout);
            }
        }
        if ($scheme !== null && !in_array($scheme, ['light', 'dark', 'auto'], true)) {
            $errors[] = 'preferredColorScheme: expected light, dark or auto.';
        }
        foreach ($assets as $asset => $url) {
            if (!str_starts_with($url, '/') || str_contains($url, '..')) {
                $errors[] = sprintf('assets.%s: expected a safe absolute URL path.', $asset);
            }
        }
        if ($errors !== []) {
            throw new ThemeManifestException($errors);
        }

        return new ThemeManifest($schema ?? 1, $id ?? '', $name ?? '', $version ?? '', $uiApi ?? '', $extends ?? '', array_values(array_unique($layouts)), $assets, $scheme);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     */
    private function string(array $data, string $key, array &$errors, ?string $path = null): ?string
    {
        if (!isset($data[$key]) || !is_string($data[$key]) || trim($data[$key]) === '') {
            $errors[] = ($path ?? $key) . ': expected a non-empty string.';
            return null;
        }
        return trim($data[$key]);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     */
    private function nullableString(array $data, string $key, array &$errors): ?string
    {
        if (!array_key_exists($key, $data) || $data[$key] === null) {
            return null;
        }
        return $this->string($data, $key, $errors);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     */
    private function integer(array $data, string $key, array &$errors): ?int
    {
        if (!isset($data[$key]) || !is_int($data[$key])) {
            $errors[] = $key . ': expected an integer.';
            return null;
        }
        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     * @return array<string, mixed>
     */
    private function object(array $data, string $key, array &$errors): array
    {
        if (!isset($data[$key]) || !is_array($data[$key]) || array_is_list($data[$key])) {
            $errors[] = $key . ': expected an object.';
            return [];
        }
        /** @var array<string, mixed> $object */
        $object = $data[$key];
        return $object;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     * @return list<string>
     */
    private function stringList(array $data, string $key, array &$errors): array
    {
        if (!isset($data[$key]) || !is_array($data[$key]) || !array_is_list($data[$key])) {
            $errors[] = $key . ': expected a list of strings.';
            return [];
        }
        /** @var list<string> $result */
        $result = [];
        foreach ($data[$key] as $value) {
            if (!is_string($value) || trim($value) === '') {
                $errors[] = $key . ': every value must be a non-empty string.';
                continue;
            }
            $result[] = trim($value);
        }
        return $result;
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $errors
     * @return array<string, string>
     */
    private function stringMap(array $data, string $key, array &$errors): array
    {
        $values = $this->object($data, $key, $errors);
        /** @var array<string, string> $result */
        $result = [];
        foreach ($values as $name => $value) {
            if (!is_string($value) || trim($name) === '' || trim($value) === '') {
                $errors[] = $key . ': every entry must contain non-empty strings.';
                continue;
            }
            $result[trim($name)] = trim($value);
        }
        return $result;
    }
}
