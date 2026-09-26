<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticatedSession;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\SessionStore;

final class ArraySessionStore implements SessionStore
{
    private ?AuthenticatedSession $session = null;
    private ?string $anonymousToken = null;
    public int $regenerations = 0;

    public function load(): ?AuthenticatedSession { return $this->session; }
    public function save(AuthenticatedSession $session): void { $this->session = $session; }
    public function regenerate(): void { $this->regenerations++; }
    public function invalidate(): void { $this->session = null; $this->anonymousToken = null; }
    public function anonymousCsrfToken(): string { return $this->anonymousToken ??= bin2hex(random_bytes(32)); }
}
