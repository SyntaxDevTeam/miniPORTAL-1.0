<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticatedSession;

interface SessionStore
{
    public function load(): ?AuthenticatedSession;
    public function save(AuthenticatedSession $session): void;
    public function regenerate(): void;
    public function invalidate(): void;
    public function anonymousCsrfToken(): string;
}
