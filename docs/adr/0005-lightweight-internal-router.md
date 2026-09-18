# ADR-0005: Lightweight internal router

Status: Accepted
Date: 2026-09-18

## Context

miniPORTAL needs named routes, path parameters, URL generation and middleware without adopting a full web framework. Routing is part of the Core HTTP boundary and must remain independent from domain modules.

## Constraints

- low cold-start and dependency cost,
- named routes and URL generation,
- HTTP method handling,
- route parameters,
- middleware pipeline,
- later support for namespaced module route registration,
- server-driven/fragment responses must remain possible.

## Considered options

- full framework routing component,
- small third-party router,
- small internal router with narrow responsibilities.

## Decision

Use a small internal router and HTTP middleware contracts owned by Core.

The initial router supports exact/static paths, full-segment named parameters, method dispatch, 404/405 responses and URL generation. More advanced patterns are added only when a real use case requires them.

## Consequences

- no routing framework dependency,
- the public surface stays intentionally small,
- route behavior is covered by direct tests,
- module route registration will later wrap this router rather than exposing internal arrays.

## Revisit trigger

Revisit if requirements such as host routing, complex regex matching or route compilation create measurable complexity beyond the narrow Core responsibility.
