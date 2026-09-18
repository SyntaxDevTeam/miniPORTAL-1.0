# ADR-0001: PHP 8.5 as runtime baseline

Status: Accepted
Date: 2026-09-18

## Context

miniPORTAL 1.0 is a new architecture-first line and does not need to preserve compatibility with the legacy runtime baseline. The project needs a clearly enforced platform target before Composer dependencies and CI are introduced.

## Constraints

- the production runtime should remain modern for the whole 1.0 line,
- CI must test the same major/minor runtime required by Composer,
- the codebase should avoid artificial compatibility layers for older PHP releases.

## Considered options

- PHP 8.4 as the minimum,
- PHP 8.5 as the minimum.

## Decision

miniPORTAL 1.0 requires PHP 8.5 or newer within the 8.x compatibility range declared by Composer (`^8.5`).

## Consequences

- CI runs PHP 8.5,
- installations on PHP 8.4 and older are rejected by Composer/doctor,
- local developer environments may still lint syntax with older runtimes when the used syntax permits it, but this does not change the supported runtime contract.

## Revisit trigger

Revisit only for a future major miniPORTAL release or when PHP support policy materially changes.
