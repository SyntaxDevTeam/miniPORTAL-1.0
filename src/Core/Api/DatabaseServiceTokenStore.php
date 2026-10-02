<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

use DateTimeImmutable;
use DateTimeZone;
use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

final readonly class DatabaseServiceTokenStore implements ServiceTokenStore
{
    private string $table;

    public function __construct(private Database $database)
    {
        $this->table = (new StorageNamespace(DatabaseServiceApiMigration::OWNER_ID))->table('tokens')->value;
    }

    public function issue(array $scopes, DateTimeImmutable $expiresAt): IssuedServiceToken
    {
        if ($scopes === [] || count($scopes) > 32) {
            throw new \InvalidArgumentException('Service token needs 1–32 scopes.');
        }
        $normalized = [];
        foreach ($scopes as $scope) {
            if (preg_match('/^(?:\*|[a-z][a-z0-9_.-]{1,119})$/D', $scope) !== 1) {
                throw new \InvalidArgumentException('Service token scope is invalid.');
            }
            $normalized[$scope] = true;
        }
        $expires = $expiresAt->setTimezone(new DateTimeZone('UTC'));
        if ($expires <= new DateTimeImmutable('now', new DateTimeZone('UTC'))) {
            throw new \InvalidArgumentException('Service token expiry must be in the future.');
        }
        $id = bin2hex(random_bytes(16));
        $secret = bin2hex(random_bytes(32));
        $this->database->execute(new SqlStatement(sprintf(
            'INSERT INTO %s (id, secret_hash, scopes_json, expires_at, revoked) '
            . 'VALUES (:id, :hash, :scopes, :expires, :revoked)', $this->table,
        ), [
            'id' => $id,
            'hash' => hash('sha256', $secret),
            'scopes' => json_encode(array_keys($normalized), JSON_THROW_ON_ERROR),
            'expires' => $expires->format('Y-m-d\TH:i:s.uP'),
            'revoked' => false,
        ]));
        return new IssuedServiceToken($id, 'mp1_' . $id . '_' . $secret);
    }

    public function authenticate(string $bearer, DateTimeImmutable $now): ?ServicePrincipal
    {
        if (preg_match('/^mp1_([a-f0-9]{32})_([a-f0-9]{64})$/D', $bearer, $matches) !== 1) {
            return null;
        }
        $row = $this->database->fetchOne(new SqlStatement(sprintf(
            'SELECT secret_hash, scopes_json, expires_at, revoked FROM %s WHERE id = :id', $this->table,
        ), ['id' => $matches[1]]));
        if ($row === null || !is_string($row['secret_hash'] ?? null) || !is_string($row['scopes_json'] ?? null)
            || !is_string($row['expires_at'] ?? null) || !is_numeric($row['revoked'] ?? null)) {
            return null;
        }
        if ((int) $row['revoked'] !== 0 || !hash_equals($row['secret_hash'], hash('sha256', $matches[2]))) {
            return null;
        }
        try {
            $expires = new DateTimeImmutable($row['expires_at']);
        } catch (\Exception) {
            return null;
        }
        if ($expires <= $now) {
            return null;
        }
        $raw = json_decode($row['scopes_json'], true);
        if (!is_array($raw) || !array_is_list($raw)) {
            return null;
        }
        $scopes = [];
        foreach ($raw as $scope) {
            if (!is_string($scope) || preg_match('/^(?:\*|[a-z][a-z0-9_.-]{1,119})$/D', $scope) !== 1) {
                return null;
            }
            $scopes[] = $scope;
        }
        return $scopes === [] ? null : new ServicePrincipal($matches[1], $scopes);
    }

    public function revoke(string $id): void
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $id) !== 1) {
            throw new \InvalidArgumentException('Service token ID is invalid.');
        }
        $this->database->execute(new SqlStatement(sprintf(
            'UPDATE %s SET revoked = :revoked WHERE id = :id', $this->table,
        ), ['revoked' => true, 'id' => $id]));
    }
}
