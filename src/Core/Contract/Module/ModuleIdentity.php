<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Contract\Module;

use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticatedSession;
use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticationManager;

/** Read-only session and CSRF access for modules. */
final readonly class ModuleIdentity
{
    public function __construct(private AuthenticationManager $authentication)
    {
    }

    public function current(): ?AuthenticatedSession
    {
        return $this->authentication->current();
    }

    public function verifyCsrf(?string $token): bool
    {
        return $this->authentication->verifyCsrf($token);
    }
}
