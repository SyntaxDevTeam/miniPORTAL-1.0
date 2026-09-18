# ADR-0004: Internal composition container

Status: Accepted
Date: 2026-09-18

## Context

Core needs a composition root before routing, events and package orchestration can be wired consistently. The container must not become a globally accessible service locator for modules.

## Constraints

- very small cold-start overhead,
- no framework lock-in,
- services are shared by default,
- circular dependencies must fail deterministically,
- modules must depend on explicit contracts/context objects rather than the container.

## Considered options

- external PSR-11 container,
- small internal container,
- manual constructor wiring without a registry.

## Decision

Use a small internal `ServiceContainer` owned by Core. It supports explicit instances and lazy factories and is used only at the application composition boundary.

The container itself is not a module-facing contract. Module code must not receive it as a general dependency.

## Consequences

- no additional runtime package is needed for DI,
- the composition graph stays visible in `CompositionRoot`,
- Core can later replace the implementation without changing module APIs,
- service-locator usage outside the composition boundary is an architecture violation.

## Revisit trigger

Revisit if compile-time container optimization or significantly more complex service decoration becomes a measured need.
