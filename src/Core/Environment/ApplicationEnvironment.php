<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Environment;

enum ApplicationEnvironment: string
{
    case Development = 'development';
    case Testing = 'testing';
    case Production = 'production';

    public static function fromEnvironment(?string $value): self
    {
        return match (strtolower(trim($value ?? 'production'))) {
            'dev', 'development' => self::Development,
            'test', 'testing' => self::Testing,
            default => self::Production,
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }
}
