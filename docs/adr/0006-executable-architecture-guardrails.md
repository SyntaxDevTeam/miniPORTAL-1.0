# ADR-0006: Executable architecture guardrails

Status: Accepted
Date: 2026-09-18

## Context

miniPORTAL 1.0 explicitly relies on architecture boundaries to keep local failures local and to prevent modules/themes from bypassing public contracts. Documentation alone is not sufficient, especially with automated and AI-assisted changes.

## Constraints

- the guardrail must run through the same `composer verify` command locally and in CI,
- the initial implementation should not add a heavy runtime dependency,
- violations must fail CI instead of only printing advisory warnings,
- module filesystem access and internal Core coupling must be detectable before merge.

## Considered options

- documentation and code review only,
- PHPStan rules only,
- a dedicated dependency-analysis package,
- a small repository scanner based on PHP tokenization.

## Decision

Keep PHPStan 2.x for static type analysis and add a separate `composer architecture` quality gate backed initially by `tools/architecture.php`.

The scanner uses PHP tokenization and directory-aware rules to enforce the first dependency boundaries. It is intentionally a build-time tool and is not part of the production runtime.

## Initial enforced rules

- Core cannot depend on domain `Modules`,
- public Core contracts cannot depend on selected Core internals,
- modules cannot depend on the internal container, package registry or raw Router,
- modules cannot directly call selected filesystem functions,
- themes cannot depend on module/package/DI infrastructure.

## Consequences

- architectural mistakes become build failures,
- AI agents receive immediate deterministic feedback,
- rules can grow independently from business code,
- the implementation can later be replaced by a specialized AST/dependency tool while retaining the same `composer architecture` contract.

## Revisit trigger

Replace or extend the scanner when token-level rules become too imprecise for the dependency graph, or when maintaining the custom scanner costs more than adopting a dedicated build-time tool.
