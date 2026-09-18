<?php

declare(strict_types=1);

$root = dirname(__DIR__);

/** @var list<string> $failures */
$failures = [];

/**
 * @return list<string>
 */
function phpFiles(string $directory): array
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
 * @param list<string> $needles
 * @param list<string> $failures
 */
function forbidStrings(string $root, string $directory, array $needles, string $rule, array &$failures): void
{
    foreach (phpFiles($directory) as $file) {
        $contents = file_get_contents($file);
        if ($contents === false) {
            $failures[] = sprintf('%s: cannot read %s', $rule, $file);
            continue;
        }

        foreach ($needles as $needle) {
            if (str_contains($contents, $needle)) {
                $relative = ltrim(str_replace($root, '', $file), DIRECTORY_SEPARATOR);
                $failures[] = sprintf('%s: %s references forbidden %s', $rule, $relative, $needle);
            }
        }
    }
}

forbidStrings(
    $root,
    $root . '/src/Core/Contract',
    [
        'SyntaxDevTeam\\MiniPortal\\Core\\DependencyInjection\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Kernel\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Logging\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Module\\Registration\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Package\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Routing\\',
    ],
    'Core contracts must not depend on implementation namespaces',
    $failures,
);

forbidStrings(
    $root,
    $root . '/src/Core',
    ['SyntaxDevTeam\\MiniPortal\\Modules\\'],
    'Core must not depend on domain modules',
    $failures,
);

forbidStrings(
    $root,
    $root . '/modules',
    [
        'SyntaxDevTeam\\MiniPortal\\Core\\DependencyInjection\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Kernel\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Logging\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Module\\Registration\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Package\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Routing\\',
    ],
    'Modules must use public Core contracts instead of internals',
    $failures,
);

forbidStrings(
    $root,
    $root . '/modules',
    [
        'file_get_contents(',
        'file_put_contents(',
        'scandir(',
        'unlink(',
        'rename(',
        'FilesystemIterator',
        'RecursiveDirectoryIterator',
    ],
    'Modules must use Filesystem capability instead of direct filesystem APIs',
    $failures,
);

forbidStrings(
    $root,
    $root . '/themes',
    [
        'SyntaxDevTeam\\MiniPortal\\Core\\DependencyInjection\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Kernel\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Package\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Module\\',
        'SyntaxDevTeam\\MiniPortal\\Core\\Routing\\',
    ],
    'Themes must stay presentation-only',
    $failures,
);

if ($failures !== []) {
    fwrite(STDERR, "Architecture violations:\n");
    foreach ($failures as $failure) {
        fwrite(STDERR, ' - ' . $failure . PHP_EOL);
    }
    exit(1);
}

fwrite(STDOUT, "Architecture checks passed.\n");
