<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Discovery;

use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestParser;
use SyntaxDevTeam\MiniPortal\Core\Package\Manifest\ManifestValidationException;

final readonly class PackageDiscovery
{
    public function __construct(private ManifestParser $parser)
    {
    }

    public function discover(string $root): DiscoveryReport
    {
        if (!is_dir($root)) {
            return new DiscoveryReport([], []);
        }

        $packages = [];
        $failures = [];
        $entries = scandir($root);
        if ($entries === false) {
            return new DiscoveryReport([], []);
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $directory = $root . DIRECTORY_SEPARATOR . $entry;
            $manifestPath = $directory . DIRECTORY_SEPARATOR . 'manifest.json';
            if (!is_dir($directory) || !is_file($manifestPath)) {
                continue;
            }

            try {
                $packages[] = new DiscoveredPackage($directory, $this->parser->parseFile($manifestPath));
            } catch (ManifestValidationException $exception) {
                $failures[] = new DiscoveryFailure($directory, $exception->errors);
            }
        }

        return new DiscoveryReport($packages, $failures);
    }
}
