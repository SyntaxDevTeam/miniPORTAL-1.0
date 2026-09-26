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

Until persistent users and roles are delivered, a deployment must explicitly
allow stable administrator identities through
`MINIPORTAL_AUTH_ADMIN_IDENTITIES`, for example `github:123456`. This is the
configuration equivalent of the controlled first-admin bootstrap described by
the source specification; matching is never based on mutable login or email.

Hardened native sessions, rotation, CSRF protection, idle/absolute expiry and
private/no-store responses from ADR-0010 remain in force.

## Consequences

The application no longer stores or verifies an administrator password.
Launching authentication requires credentials for at least one supported
provider and the provider's stable subject for the first administrator.
Persistent local user/role records, atomic first-owner creation, provider
linking, rate limiting and recovery remain subsequent Security Core stages.

## Revisit trigger

Replace the environment allow-list with the persistent external-identity and
role repositories. Keep the provider, OAuth transaction and session contracts
compatible where practical.
