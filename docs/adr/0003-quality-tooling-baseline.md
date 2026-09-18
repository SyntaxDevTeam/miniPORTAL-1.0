# ADR-0003: PHPUnit and PHPStan as initial quality tooling

Status: Accepted
Date: 2026-09-18

## Context

The first Core slice needs executable tests and strict static analysis before more infrastructure is added. The tooling must remain conventional enough for contributors and AI agents to run through one `composer verify` command.

## Constraints

- PHP 8.5 runtime baseline,
- reusable contract test suites later,
- strict static analysis,
- minimal framework lock-in.

## Considered options

- PHPUnit directly,
- a higher-level test DSL layered over PHPUnit,
- PHPStan for static analysis,
- alternative static analyzers.

## Decision

Use PHPUnit 13.x as the initial test framework and PHPStan 2.x at maximum analysis level. The project may add specialized architecture tooling later, but it must remain callable through `composer verify`.

## Consequences

- tests use standard PHPUnit semantics,
- contract suites can be ordinary reusable PHPUnit tests/traits,
- PHPStan becomes the first static quality gate,
- architecture rules remain a separate future gate rather than being confused with type analysis.

## Revisit trigger

Revisit if either tool blocks a required architecture test pattern or becomes incompatible with the supported PHP runtime.
