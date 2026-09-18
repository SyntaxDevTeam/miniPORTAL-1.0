# ADR-0002: Canonical package manifests use JSON

Status: Accepted
Date: 2026-09-18

## Context

Package discovery must inspect metadata without executing package code. The runtime therefore needs one deterministic, machine-validated manifest format.

## Constraints

- data-only discovery,
- deterministic parsing,
- schema versioning,
- simple tooling for CI and package preflight,
- no requirement to execute PHP to learn package metadata.

## Considered options

- JSON only,
- YAML authoring with runtime conversion,
- PHP array files.

## Decision

The canonical runtime manifest is `manifest.json`. YAML is not part of the 1.0 runtime contract. Authoring conveniences may be added later only if they compile to the same canonical JSON model before package activation.

## Consequences

- Core discovery reads data only,
- manifests can be validated before autoloading an entrypoint,
- package tooling has one canonical representation.

## Revisit trigger

Revisit only if package authors demonstrate a concrete authoring problem that cannot be solved by tooling around JSON.
