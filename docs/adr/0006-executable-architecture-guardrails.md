# ADR-0006: Executable architecture guardrails

Status: Accepted
Date: 2026-09-18

## Context

miniPORTAL 1.0 explicitly requires architecture rules to be machine-enforced rather than remembered by contributors or AI agents. PHPStan is excellent at type analysis, but it does not by itself encode all repository layer boundaries or forbidden APIs.

## Constraints

- rules must run locally and in CI through `composer verify`,
- the initial implementation should remain lightweight,
- diagnostics must point to the violating file and rule,
- rules should be easy to extend as Modules, Themes, UI and Providers appear,
- architecture checks must complement rather than replace PHPStan.

## Considered options

- documentation only,
- PHPStan custom extensions from the start,
- an external dependency architecture package,
- a small repository-specific checker plus PHPStan.

## Decision

Introduce `tools/architecture.php` as the first architecture boundary checker and expose it through:

```bash
composer architecture
```

It runs as part of `composer verify`.

The initial rules prevent:

- Core contracts from importing selected Core implementation namespaces,
- Core from depending on domain Modules,
- Modules from reaching internal Core namespaces,
- Modules from directly using filesystem APIs that must later go through the Filesystem capability,
- Themes from depending on Core infrastructure/business implementation namespaces.

## Consequences

- architecture violations fail CI before merge,
- rules can evolve together with repository structure,
- PHPStan remains responsible for strict type/static analysis,
- the lightweight checker may later be replaced or supplemented by AST/dependency-graph tooling without changing the `composer architecture` contract.

## Revisit trigger

Revisit when namespace/string checks become insufficient for language constructs used by the codebase or when the number of architecture rules makes AST-level tooling materially simpler.
