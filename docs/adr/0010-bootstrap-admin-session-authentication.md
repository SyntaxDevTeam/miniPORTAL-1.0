# ADR-0010: Bootstrap administrator session authentication

Status: Superseded by ADR-0011
Date: 2026-09-26

## Context

The first deployable miniPORTAL panel needs an authentication boundary before
the persistent user, role and permission administration module is available.
Leaving `/admin` public until that later milestone is not acceptable.

## Constraints

- plaintext passwords must not be stored in configuration,
- browser mutations require CSRF protection,
- login must rotate the session identifier,
- sessions need idle and absolute expiry,
- authenticated responses must not enter shared caches,
- the bootstrap mechanism must be replaceable without changing modules.

## Considered options

1. Keep the panel private using only an Apache password file.
2. Wait for the complete database-backed identity model.
3. Add a Core-owned bootstrap administrator backed by environment configuration
   and a replaceable session contract.

## Decision

Use option 3. Core accepts one bootstrap administrator username and a
`password_hash()` value from runtime configuration. Authentication state is
stored behind `SessionStore`; production uses hardened native PHP sessions and
tests use an in-memory provider. Login and logout rotate or invalidate session
state, POST requests verify CSRF tokens, and `/admin` responses use
`Cache-Control: private, no-store`.

This is an operational bootstrap identity, not the final authorization model.
Authenticated requests receive the temporary Core administrator permission set.

## Consequences

The panel can be safely protected before database-backed users exist. Operators
must provision the password hash outside the repository. Multi-user identity,
role assignment, recovery, MFA and centralized login rate limiting remain
required before stable 1.0.

## Revisit trigger

Replace bootstrap verification when the persistent identity provider and role
storage are ready. Keep the session and request-context contracts compatible
where practical.

## Supersession note

The password-based verifier and local login form were removed before release.
ADR-0011 retains the hardened session boundary but replaces credential
verification with external OAuth/OIDC identities, matching the source product
specification.
