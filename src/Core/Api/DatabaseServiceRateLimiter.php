<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Api;

use SyntaxDevTeam\MiniPortal\Library\Storage\Contract\Database;
use SyntaxDevTeam\MiniPortal\Library\Storage\Exception\QueryFailed;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\StorageNamespace;

/** Atomic counter updates; concurrent first inserts retry the conditional update. */
final readonly class DatabaseServiceRateLimiter implements ServiceRateLimiter
{
    private string $table;

    public function __construct(private Database $database)
    {
        $this->table = (new StorageNamespace(DatabaseServiceApiMigration::OWNER_ID))->table('rate_limits')->value;
    }

    public function allow(string $tokenId, int $epochSeconds, int $perMinute): bool
    {
        if (preg_match('/^[a-f0-9]{32}$/D', $tokenId) !== 1 || $perMinute < 1 || $perMinute > 10_000) {
            throw new \InvalidArgumentException('Service rate limit parameters are invalid.');
        }
        $window = intdiv($epochSeconds, 60) * 60;
        $parameters = ['id' => $tokenId, 'window' => $window, 'limit' => $perMinute];
        $update = new SqlStatement(sprintf('UPDATE %s SET used = used + 1 '
            . 'WHERE token_id = :id AND window_start = :window AND used < :limit', $this->table), $parameters);
        if ($this->database->execute($update) === 1) {
            return true;
        }
        try {
            $this->database->execute(new SqlStatement(sprintf(
                'INSERT INTO %s (token_id, window_start, used) VALUES (:id, :window, 1)', $this->table,
            ), ['id' => $tokenId, 'window' => $window]));
            return true;
        } catch (QueryFailed $exception) {
            $previous = $exception->getPrevious();
            if (!$previous instanceof \PDOException || !str_starts_with((string) $previous->getCode(), '23')) {
                throw $exception;
            }
            return $this->database->execute($update) === 1;
        }
    }
}
