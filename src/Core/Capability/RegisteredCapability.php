<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Capability;

final readonly class RegisteredCapability
{
    public function __construct(
        public string $name,
        public string $version,
        public string $providerId,
        public object $service,
    ) {
        if (preg_match('/^[a-z][a-z0-9.-]*$/', $name) !== 1) {
            throw new \InvalidArgumentException(
                'Capability name must use lowercase letters, digits, dots and dashes.',
            );
        }

        if (preg_match('/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?$/', $version) !== 1) {
            throw new \InvalidArgumentException('Capability version must be valid SemVer.');
        }

        if (preg_match('/^(?:@core|[a-z][a-z0-9-]*(?:\.[a-z0-9-]+)*)$/', $providerId) !== 1) {
            throw new \InvalidArgumentException('Capability provider ID is invalid.');
        }
    }
}
