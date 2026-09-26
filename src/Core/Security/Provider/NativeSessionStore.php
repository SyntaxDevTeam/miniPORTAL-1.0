<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Security\AuthenticatedSession;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\SessionStore;

final class NativeSessionStore implements SessionStore
{
    private const KEY = 'miniportal_auth';
    private const ANONYMOUS_CSRF = 'miniportal_anonymous_csrf';

    public function __construct(bool $secureCookie)
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('MINIPORTALSESSID');
        if (!session_start([
            'use_strict_mode' => 1,
            'use_only_cookies' => 1,
            'cookie_httponly' => 1,
            'cookie_secure' => $secureCookie ? 1 : 0,
            'cookie_samesite' => 'Strict',
            'cookie_path' => '/',
        ])) {
            throw new \RuntimeException('Unable to start the authentication session.');
        }
    }

    public function load(): ?AuthenticatedSession
    {
        $data = $_SESSION[self::KEY] ?? null;
        if (!is_array($data) || !isset($data['principal'], $data['created'], $data['last_seen'], $data['csrf'])
            || !is_string($data['principal']) || !is_int($data['created']) || !is_int($data['last_seen']) || !is_string($data['csrf'])) {
            return null;
        }
        try {
            return new AuthenticatedSession($data['principal'], $data['created'], $data['last_seen'], $data['csrf']);
        } catch (\InvalidArgumentException) {
            $this->invalidate();
            return null;
        }
    }

    public function save(AuthenticatedSession $session): void
    {
        $_SESSION[self::KEY] = [
            'principal' => $session->principalId,
            'created' => $session->createdAt,
            'last_seen' => $session->lastSeenAt,
            'csrf' => $session->csrfToken,
        ];
        unset($_SESSION[self::ANONYMOUS_CSRF]);
    }

    public function regenerate(): void
    {
        if (!session_regenerate_id(true)) {
            throw new \RuntimeException('Unable to rotate the session identifier.');
        }
    }

    public function invalidate(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public function anonymousCsrfToken(): string
    {
        $token = $_SESSION[self::ANONYMOUS_CSRF] ?? null;
        if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
            $token = bin2hex(random_bytes(32));
            $_SESSION[self::ANONYMOUS_CSRF] = $token;
        }
        return $token;
    }
}
