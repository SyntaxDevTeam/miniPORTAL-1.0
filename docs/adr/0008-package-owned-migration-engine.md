# ADR-0008: Package-owned, plan-first migration engine

Status: Accepted

Date: 2026-09-19

## Context

miniPORTAL 1.0 must support fresh installations and upgrades from an existing
schema without running package code or DDL during discovery. Q-004 requires an
owner/package ledger, a reviewable plan, dry-run metadata, reversibility and
destructive-change flags, and preflight hooks.

The production database is MySQL/MariaDB, while SQLite remains useful for fast
contract tests. DDL transaction behaviour is not portable: MySQL/MariaDB can
implicitly commit schema statements, so wrapping every migration in the
`Database::transaction()` contract would promise rollback semantics that the
platform cannot guarantee.

## Constraints

- migration discovery and planning must be read-only apart from creating the
  Core-owned ledger,
- migration identity and checksum are immutable after application,
- each package owns an ordered schema history,
- SQL values remain parameterized through `SqlStatement`,
- destructive work must be explicit and policy-gated,
- code rollback must not imply data rollback,
- failed migrations must remain diagnosable and safe to retry,
- the baseline must work through the public `Database` contract without
  exposing PDO.

## Considered options

1. run `install.sql` automatically when a package is discovered,
2. rely on a framework-specific migration package,
3. execute all DDL in a nominal transaction,
4. use ordered, package-owned migration definitions with a Core ledger and a
   separate plan/apply boundary.

## Decision

Use option 4.

Each migration definition declares:

- package owner ID and immutable migration ID,
- source and target schema versions,
- ordered `up` statements and optional `down` statements,
- expand/transition/contract phase,
- destructive, backup and expected-lock metadata,
- zero or more read-only preflight checks.

The planner compares definitions with the Core-owned
`miniportal_schema_migrations` ledger. It reports pending/applied entries,
checksum drift, removed definitions, broken version chains and preflight
failures. Planning never executes migration statements. Applied migrations are
required to form a prefix of the declared history.

The runner accepts only an executable plan. Destructive migrations require an
explicit execution policy and migrations marked as requiring a backup also
require an explicit backup confirmation. The baseline executes statements in
order and records the ledger row only after every statement succeeds.

The baseline deliberately does not claim transactional DDL. A failed migration
can therefore leave provider-specific partial schema work while remaining
unrecorded. Migration authors must use retry-safe expand/contract operations and
preflight checks; recovery instructions belong to release metadata. A future
provider capability may opt into stronger atomic DDL where it can prove it.

## Legacy upgrades

Existing installations are not modified automatically. A future upgrade wizard
will build the same plan against a copy/staging database, identify or record an
explicit legacy baseline, require a backup where indicated, and only then call
the runner. Legacy table and `.env` compatibility is handled by migration and
configuration adapters, not by changing discovery behaviour.

## Consequences

- replacing application files alone cannot mutate the database,
- operators can inspect pending work and risk metadata before activation,
- checksum drift blocks activation instead of silently rewriting history,
- package schema ownership is auditable,
- reversible metadata is honest but database rollback remains an explicit
  operation outside the first baseline,
- MySQL/MariaDB partial-DDL recovery remains a documented operational concern.

## Revisit trigger

Revisit when provider-specific atomic DDL capabilities are implemented, when the
legacy upgrade wizard needs a persisted baseline-adoption workflow, or when a
non-SQL provider must participate in package migrations.
