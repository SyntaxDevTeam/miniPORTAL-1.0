<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;

/** Read-only snapshots: these calls never create an identity or update last-login data. */
interface AccountDirectory
{
    public function find(string $id): ?UserAccount;

    /** @return list<UserAccount> Ordered by creation date and ID. */
    public function listing(int $limit = 20, int $offset = 0): array;
}
