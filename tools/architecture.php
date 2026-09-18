<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);

/**
 * @return list<string>
 */
function collectPhpFiles(string $directory): array
{
    if (!is_dir($directory)) {
        return [];
    }

    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if ($file->isFile() && $file->getExtension() === 'php') {
            $files[] = $file->getPathname();
        }
    }

    sort($files);

    return $files;
}

/**
 * @return list<string>
 */
function qualifiedNames(string $source): array
{
    $names = [];

    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            continue;
        }

        if ($token[0] === T_NAME_QUALIFIED || $token[0] === T_NAME_FULLY_QUALIFIED) {
            $names[] = ltrim($token[1], '\\');
        }
    }

    return array_values(array_unique($names));
}

function codeWithoutCommentsAndStrings(string $source): string
{
    $result = '';

    foreach (token_get_all($source) as $token) {
        if (!is_array($token)) {
            $result .= $token;
            continue;
        }

        if (in_array($token[0], [
            T_COMMENT,
            T_DOC_COMMENT,
            T_CONSTANT_ENCAPSED_STRING,
            T_ENCAPSED_AND_WHITESPACE,
        ], true)) {
            $result .= ' ';
            continue;
        }

        $result .= $token[1];
    }

    return $result;
}

/**
 * @param list<string> $forbiddenPrefixes
 * @return list<string>
 */
function namespaceViolations(string $file, array $forbiddenPrefixes): array
{
    $source = file_get_contents($file);
    if ($source === false) {
        return [sprintf('%s: cannot read file', $file)];
    }

    $violations = [];
    foreach (qualifiedNames($source) as $name) {
        foreach ($forbiddenPrefixes as $prefix) {
            if (str_starts_with($name, $prefix)) {
                $violations[] = sprintf('%s: forbidden dependency %s', $file, $name);
            }
        }
    }

    return $violations;
}

/**
 * @param list<string> $forbiddenFunctions
 * @return list<string>
 */
function functionViolations(string $file, array $forbiddenFunctions): array
{
    $source = file_get_contents($file);
    if ($source === false) {
        return [sprintf('%s: cannot read file', $file)];
    }

    $code = codeWithoutCommentsAndStrings($source);
    $violations = [];

    foreach ($forbiddenFunctions as $function) {
        $pattern = '/(?<!->)(?<!::)\\b' . preg_quote($function, '/') . '\\s*\\(/i';
        if (preg_match($pattern, $code) === 1) {
            $violations[] = sprintf('%s: forbidden direct function call %s()', $file, $function);
        }
    }

    return $violations;
}

/** @var list<string> $violations */
$violations = [];

$coreFiles = collectPhpFiles($projectRoot . '/src/Core');
foreach ($coreFiles as $file) {
    array_push(
        $violations,
        ...namespaceViolations($file, [
            'SyntaxDevTeam\\MiniPortal\\Modules\\',
        ]),
    );
}

$contractFiles = collectPhpFiles($projectRoot . '/src/Core/Contract');
foreach ($contractFiles as $file) {
    array_push(
        $violations,
        ...namespaceViolations($file, [
            'SyntaxDevTeam\\MiniPortal\\Core\\DependencyInjection\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Logging\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Module\\Registration\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Package\\Registry\\',
        ]),
    );
}

$moduleFiles = collectPhpFiles($projectRoot . '/modules');
foreach ($moduleFiles as $file) {
    array_push(
        $violations,
        ...namespaceViolations($file, [
            'SyntaxDevTeam\\MiniPortal\\Core\\DependencyInjection\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Package\\Registry\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Routing\\Router',
        ]),
        ...functionViolations($file, [
            'file_get_contents',
            'file_put_contents',
            'unlink',
            'rename',
            'scandir',
        ]),
    );
}

$themeFiles = collectPhpFiles($projectRoot . '/themes');
foreach ($themeFiles as $file) {
    array_push(
        $violations,
        ...namespaceViolations($file, [
            'SyntaxDevTeam\\MiniPortal\\Core\\DependencyInjection\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Module\\',
            'SyntaxDevTeam\\MiniPortal\\Core\\Package\\',
        ]),
    );
}

if ($violations !== []) {
    fwrite(STDERR, "Architecture violations detected:" . PHP_EOL);
    foreach (array_values(array_unique($violations)) as $violation) {
        fwrite(STDERR, ' - ' . $violation . PHP_EOL);
    }

    exit(1);
}

fwrite(STDOUT, "Architecture guardrails passed." . PHP_EOL);
