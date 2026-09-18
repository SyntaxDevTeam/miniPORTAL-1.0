# ADR-0007: Thin PDO-backed storage abstraction

Status: Accepted  
Date: 2026-09-18

## Context

Milestone 2 requires a Database/Storage contract with explicit connection lifecycle, package ownership and transaction semantics. Q-003 asks whether miniPORTAL should expose PDO directly, build or adopt a query builder, or introduce a larger ORM.

The platform needs predictable storage primitives for Core and package-owned repositories, while avoiding framework lock-in and preventing modules from opening ad-hoc database connections.

## Constraints

- PHP 8.5 is the runtime baseline.
- SQLite, MariaDB/MySQL and PostgreSQL must remain viable provider targets.
- modules must not receive raw PDO connections,
- dynamic values must be parameterized,
- transactions need portable baseline semantics,
- migrations remain a separate lifecycle concern and must not run during discovery,
- the baseline should stay small enough to audit and test at PHPStan level=max.

## Considered options

1. expose PDO directly to modules,
2. adopt a full ORM,
3. adopt/build a general query builder,
4. expose a small SQL execution contract backed by PDO and keep repository SQL package-owned.

## Decision

Use a thin public SQL storage contract backed initially by PDO.

The public contract exposes:

- parameterized `SqlStatement` values,
- `fetchOne`,
- `fetchAll`,
- `execute`,
- explicit transaction callback semantics,
- transaction-state inspection.

PDO itself remains provider-internal. Package/domain code owns repository classes and may own SQL appropriate to its storage contract, but it must not open independent connections or interpolate untrusted values into SQL.

Dynamic values are always passed separately as statement parameters. Dynamic SQL identifiers must use validated `SqlIdentifier` / `StorageNamespace` values rather than user-provided strings.

The baseline deliberately does not implement an ORM or generic query-builder DSL. If repeated cross-database SQL composition becomes a measurable maintenance problem, a later ADR may introduce a focused abstraction without changing the transaction/service boundary.

## Package namespace

`StorageNamespace` derives stable package-owned table identifiers from the package ID plus a validated local table name. This gives packages a deterministic ownership boundary without pretending that every supported database has identical schema-namespace behavior.

The migration ledger remains responsible for tracking ownership and applied schema changes.

## Transaction semantics

The baseline guarantees one explicit transaction boundary. Nested transactions are rejected instead of emulating savepoints inconsistently across providers.

Exceptions thrown by the application callback are rethrown after rollback. Provider failures to begin, commit or roll back are mapped to stable storage exceptions.

## Consequences

- repository code is explicit and easy to inspect,
- Core can swap PDO connection configuration without leaking credentials or PDO objects into modules,
- SQLite can provide fast integration fixtures,
- SQL dialect differences remain visible where they actually exist,
- a full migration engine is still required before package schema activation.

## Revisit trigger

Revisit when at least two production providers demonstrate repeated query-construction logic that a focused query builder would materially simplify, or when non-SQL storage must satisfy the same higher-level repository contract.
