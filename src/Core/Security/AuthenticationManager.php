<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\SessionStore;
use SyntaxDevTeam\MiniPortal\Library\Clock\Contract\Clock;

final readonly class AuthenticationManager
{
    public function __construct(
        private AuthenticationSettings $settings,
        private SessionStore $sessions,
        private Clock $clock,
    ) {
    }

    public function login(string $username, string $password): bool
    {
        if (!$this->settings->verifies($username, $password)) {
            return false;
        }
        $now = $this->clock->now()->getTimestamp();
        $this->sessions->regenerate();
        $this->sessions->save(new AuthenticatedSession($this->settings->username, $now, $now, bin2hex(random_bytes(32))));
        return true;
    }

    public function current(): ?AuthenticatedSession
    {
        $session = $this->sessions->load();
        if ($session === null) {
            return null;
        }
        $now = $this->clock->now()->getTimestamp();
        if ($now - $session->lastSeenAt > $this->settings->idleTimeoutSeconds
            || $now - $session->createdAt > $this->settings->absoluteTimeoutSeconds) {
            $this->sessions->invalidate();
            return null;
        }
        $session = $session->touchedAt($now);
        $this->sessions->save($session);
        return $session;
    }

    public function csrfToken(): string
    {
        $session = $this->current();
        return $session === null ? $this->sessions->anonymousCsrfToken() : $session->csrfToken;
    }

    public function verifyCsrf(?string $token): bool
    {
        return $token !== null && hash_equals($this->csrfToken(), $token);
    }

    public function logout(): void
    {
        $this->sessions->invalidate();
    }
}
