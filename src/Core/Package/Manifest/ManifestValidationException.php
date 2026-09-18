<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Package\Manifest;

final class ManifestValidationException extends \RuntimeException
{
    /** @param list<ManifestValidationError> $errors */
    public function __construct(public readonly array $errors)
    {
        parent::__construct(sprintf('Package manifest validation failed with %d error(s).', count($errors)));
    }
}
