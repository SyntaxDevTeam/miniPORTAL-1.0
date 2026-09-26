<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Contract;

use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;

interface IdentityAccountRepository
{
    /** Resolves an existing identity or creates the appropriate first-owner/pending account. */
    public function resolve(ExternalIdentity $identity): UserAccount;
}
