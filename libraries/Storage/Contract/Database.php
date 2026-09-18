<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Library\Storage\Contract;

use Closure;
use SyntaxDevTeam\MiniPortal\Library\Storage\Model\SqlStatement;

interface Database
{
    /** @return array<string, mixed>|null */
    public function fetchOne(SqlStatement $statement): ?array;

    /** @return list<array<string, mixed>> */
    public function fetchAll(SqlStatement $statement): array;

    public function execute(SqlStatement $statement): int;

    /**
     * @template T
     * @param Closure(self): T $callback
     * @return T
     */
    public function transaction(Closure $callback): mixed;

    public function isInTransaction(): bool;
}
