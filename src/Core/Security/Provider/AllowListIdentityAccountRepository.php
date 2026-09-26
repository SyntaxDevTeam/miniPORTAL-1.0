<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security\Provider;

use SyntaxDevTeam\MiniPortal\Core\Configuration\AuthenticationSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\AccountStatus;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityAccountRepository;
use SyntaxDevTeam\MiniPortal\Core\Security\ExternalIdentity;
use SyntaxDevTeam\MiniPortal\Core\Security\UserAccount;

final readonly class AllowListIdentityAccountRepository implements IdentityAccountRepository
{
    public function __construct(private AuthenticationSettings $settings)
    {
    }

    public function resolve(ExternalIdentity $identity): UserAccount
    {
        $allowed = $this->settings->permits($identity->provider, $identity->subject);
        return new UserAccount(
            $identity->principalId(),
            $identity->displayName,
            $identity->email,
            $identity->avatarUrl,
            $allowed ? AccountStatus::Active : AccountStatus::Pending,
            $allowed ? ['owner'] : ['user'],
            $allowed ? ['*'] : [],
        );
    }
}
