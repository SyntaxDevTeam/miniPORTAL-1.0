<?php

declare(strict_types=1);

namespace SyntaxDevTeam\MiniPortal\Core\Security;

use SyntaxDevTeam\MiniPortal\Core\Configuration\IdentityProviderSettings;
use SyntaxDevTeam\MiniPortal\Core\Security\Contract\IdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\DiscordIdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\GitHubIdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\GoogleIdentityProvider;
use SyntaxDevTeam\MiniPortal\Core\Security\Provider\MicrosoftIdentityProvider;
use SyntaxDevTeam\MiniPortal\Library\Http\Contract\HttpClient;

final readonly class IdentityProviderFactory
{
    public function __construct(private HttpClient $http)
    {
    }

    public function create(IdentityProviderSettings $settings): IdentityProvider
    {
        $arguments = [$this->http, $settings->clientId, $settings->clientSecret, $settings->callbackUrl];
        return match ($settings->name) {
            'github' => new GitHubIdentityProvider(...$arguments),
            'google' => new GoogleIdentityProvider(...$arguments),
            'microsoft' => new MicrosoftIdentityProvider(...$arguments),
            'discord' => new DiscordIdentityProvider(...$arguments),
            default => throw new \LogicException('Unsupported identity provider.'),
        };
    }
}
