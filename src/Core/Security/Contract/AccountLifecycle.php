<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;

interface AccountLifecycle
{
    public function changeStatus(
        string $accountId,
        AccountStatus $status,
        string $actor,
        string $correlationId,
    ): void;
}
