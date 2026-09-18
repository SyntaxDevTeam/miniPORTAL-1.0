<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Diagnostics;

final class Doctor
{
    /** @return array<string, array{ok: bool, detail: string}> */
    public function checks(): array
    {
        return [
            'php' => [
                'ok' => version_compare(PHP_VERSION, '8.5.0', '>='),
                'detail' => sprintf('PHP %s; required >= 8.5.0', PHP_VERSION),
            ],
            'json' => [
                'ok' => extension_loaded('json'),
                'detail' => extension_loaded('json') ? 'ext-json loaded' : 'ext-json missing',
            ],
            'random' => [
                'ok' => function_exists('random_bytes'),
                'detail' => function_exists('random_bytes') ? 'CSPRNG available' : 'random_bytes unavailable',
            ],
        ];
    }

    public function isHealthy(): bool
    {
        foreach ($this->checks() as $check) {
            if (!$check['ok']) {
                return false;
            }
        }
        return true;
    }
}
