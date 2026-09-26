# ADR-0011: External identity authentication

Status: Accepted
Date: 2026-09-26

## Context

The miniPORTAL source specification defines GitHub, Google, Microsoft and
Discord as normal login providers. A local password is only a possible
emergency mechanism, not the launch baseline. ADR-0010 incorrectly selected a
local password bootstrap and is superseded by this decision.

## Constraints

- provider identities are keyed by stable `(provider, subject)` pairs,
- authorization remains local and must not trust provider roles,
- authorization-code flows require one-time `state`; providers supporting it
  use PKCE, and OIDC uses `nonce`, signature and claims validation,
- secrets remain in environment/runtime configuration and never enter logs,
- the session boundary remains independent from provider adapters,
- an unknown external identity must never receive administrator access.

## Decision

Core exposes an `IdentityProvider` contract and adapters for GitHub, Google,
Microsoft and Discord. `OAuthFlow` creates a cryptographically random,
single-use transaction valid for ten minutes. GitHub, Google and Microsoft use
S256 PKCE; Google additionally validates the RS256 signature, issuer, audience,
expiry, issued-at and nonce of its ID token.

With database storage configured, external identities resolve to persistent
local accounts. A single bootstrap row serializes the first-login decision: the
first verified identity atomically becomes an active `owner`, while subsequent
unknown identities become `pending` users with the local `user` role. Matching
is never based on mutable login or email.

Database-less development may explicitly allow stable administrator identities
through `MINIPORTAL_AUTH_ADMIN_IDENTITIES`, for example `github:123456`. This
fallback does not create persistent users and is not the production model.

Hardened native sessions, rotation, CSRF protection, idle/absolute expiry and
private/no-store responses from ADR-0010 remain in force. The authenticated
session carries an immutable permission snapshot derived from local roles; the
request context no longer grants wildcard access merely because a session
exists. OAuth starts and callbacks are rate-limited independently per provider
and browser session before an external request is made.

## Consequences

The application no longer stores or verifies an administrator password.
Launching authentication requires credentials for at least one supported
provider and an applied `core.security` migration. Persistent local accounts,
external identity links, the atomic first-owner bootstrap and baseline local
roles are available. Provider linking UI, permission mapping, rate limiting and
recovery remain subsequent Security Core stages.

## Revisit trigger

Remove the database-less allow-list once the installer always provisions
database storage. Keep the provider, identity repository, OAuth transaction and
session contracts compatible where practical.
