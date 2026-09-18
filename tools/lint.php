<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$paths = ['src', 'libraries', 'tests', 'tools', 'public', 'bin'];
$failed = false;

foreach ($paths as $relative) {
    $path = $root . DIRECTORY_SEPARATOR . $relative;
    if (!is_dir($path)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $command = sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file->getPathname()));
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $failed = true;
            fwrite(STDERR, implode(PHP_EOL, $output) . PHP_EOL);
        }
        $output = [];
    }
}

exit($failed ? 1 : 0);
