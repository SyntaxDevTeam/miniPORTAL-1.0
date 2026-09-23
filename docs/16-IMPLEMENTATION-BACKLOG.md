# 16 — Implementation Backlog

Ten dokument przekłada roadmapę na epiki i konkretne pakiety pracy. Nie jest trackerem statusu; po rozpoczęciu implementacji zadania mogą zostać przeniesione do Issues/Projects, a dokument pozostaje mapą zakresu.

## Epic A — Repository and Tooling Foundation

### A1. Composer skeleton

- utworzyć `composer.json`,
- PSR-4 namespaces,
- scripts placeholder `verify`,
- dev dependencies dla test/static tooling po ADR,
- minimalny PHP version constraint.

**Acceptance:** fresh clone + `composer install` + minimal command działa.

### A2. Directory boundaries

- `src/Core`,
- `src/UI`,
- `libraries`,
- `providers`,
- `themes/base`,
- `modules`,
- `tests/*`.

**Acceptance:** architecture tool rozpoznaje warstwy.

### A3. CI bootstrap

- lint,
- Composer validate,
- minimal tests,
- docs links/lint opcjonalnie.

## Epic B — Kernel

### B1. Bootstrap and Environment

- front controller,
- environment loader,
- minimal fatal/error handler,
- production/dev mode.

### B2. Container/Composition Root

- wybór ADR,
- core service definitions,
- immutable/controlled bootstrap phases.

### B3. Request Context

- correlation ID,
- principal placeholder,
- locale/timezone hooks,
- security context.

### B4. Router

- named routes,
- module namespace,
- middleware,
- URL generation.

### B5. Error taxonomy

- typed categories,
- safe error page,
- log mapping.

### B6. CLI

- `doctor`,
- `verify-runtime`,
- package diagnostic skeleton.

## Epic C — Package/Module Platform

### C1. Manifest schema

- canonical JSON schema,
- parser,
- validation errors z path/field.

### C2. Discovery

- discover manifest without loading code,
- duplicate ID detection,
- release registry.

### C3. Dependency resolver

- semver constraints,
- required/optional,
- capability providers,
- cycle detection,
- diagnostic report.

### C4. Lifecycle state machine

- explicit enum/state transitions,
- persistence,
- audit hooks.

### C5. Module Dispatcher

- runtime boundary,
- health,
- telemetry,
- error component.

### C6. Failure fixtures

- wszystkie fixture opisane w testing docs.

## Epic D — Service Contracts

### D1. CacheContract

- Null/Array provider,
- APCu provider,
- contract test suite.

### D2. FilesystemContract

- path value object,
- scope,
- policy,
- errors,
- local provider,
- traversal/symlink tests,
- atomic writes.

### D3. Database/Storage

- connection lifecycle,
- package namespace/migration hooks,
- transaction contract.

Instalator ma oferować jawny wybór MySQL/MariaDB albo PostgreSQL. Konfiguracja
PDO posiada typowany wybór silnika i bezpieczne fabryki DSN. Przed wydaniem
produkcyjne migracje, durable Jobs i inne providery DB muszą przechodzić testy
integracyjne na obu silnikach; SQLite pozostaje szybkim fixture testowym.

### D4. Jobs

- Job definition/status/progress,
- minimal local/DB-backed runner decision.

Baseline kontraktu gotowy: typowane zadanie i status, postęp, idempotencja,
widok schedulera ograniczony do pakietu,
lokalny provider pamięciowy oraz testy kontraktowe. ADR-0009 wybiera DB-backed
queue i worker CLI dla produkcji. Trwały provider posiada plan-first migrację,
atomowy claim, lease recovery, heartbeat, limit prób i ochronę przed zapisem
starego workera. Zaufany rejestr handlerów i wykonanie pojedynczego zadania są
gotowe; wiring długotrwałego procesu CLI pozostaje do wykonania razem z loaderem
konfiguracji i aktywnych pakietów.

### D5. Realtime

- channel/event abstraction,
- SSE implementation later,
- transport-neutral API.

Baseline kontraktu gotowy: kanały ograniczone do pakietu, walidowane zdarzenia
data-only, izolacja błędów subskrybentów oraz pamięciowy provider testowy.
Transport SSE i komunikacja między procesami pozostają zakresem Milestone 5.

### D6. HTTP client

- timeout/retry policy,
- diagnostics hooks.

Baseline gotowy: publiczny kontrakt żądania i odpowiedzi, provider PHP streams,
ograniczony timeout i ponawianie metod idempotentnych, bezpieczny hook
diagnostyczny oraz testy polityki ponawiania i walidacji wejścia.

## Epic E — UI Core

### E1. Component base

- component identity,
- props/value objects,
- tree composition.

### E2. PageDefinition

- title,
- layout role,
- regions,
- breadcrumbs,
- actions.

### E3. Forms

- fields,
- validation mapping,
- errors,
- actions.

### E4. Tables

- columns,
- source,
- sort/filter/search,
- pagination,
- row/bulk actions.

### E5. States

- loading,
- empty,
- error,
- permission denied,
- degraded.

### E6. Fragment rendering contract

- full page,
- named region,
- single component.

## Epic F — Theme Engine

### F1. Base Theme

- wszystkie komponenty UI API,
- semantic HTML,
- accessibility baseline.

### F2. Renderer registry

- exact override,
- parent inheritance,
- Base fallback.

### F3. Layout registry

- application,
- auth,
- fullscreen,
- emergency.

### F4. Tokens/assets

- semantic tokens,
- hashed assets.

### F5. Second reference theme

Ma być celowo różny strukturalnie: np. centered top-navigation zamiast full-width sidebar. Służy testowi architektury, nie marketingowi.

### F6. UI Catalog

- fixtures wszystkich komponentów,
- theme switch,
- viewport modes.

## Epic G — Interaction Layer

### G1. htmx adapter

- actions,
- fragment requests,
- target/swap details ukryte w rendererze.

### G2. History/navigation

- deep links,
- title,
- focus,
- scroll behavior.

### G3. SSE

- connection manager,
- reconnect,
- auth,
- component updates.

### G4. WebSocket adapter

- tylko po realnym use case; API contract wcześniej.

### G5. View transitions/motion

- progressive,
- reduced motion.

## Epic H — PWA/Performance

### H1. Manifest/installability

### H2. Service Worker allowlist strategy

### H3. Offline shell/status

### H4. Cache providers/policies

### H5. Asset lazy loading

### H6. Performance budgets + benchmark fixture

## Epic I — Safe Package Lifecycle

### I1. Quarantine

### I2. Integrity/archive checks

### I3. Isolated PHP CLI preflight

### I4. Migration plan/dry-run

### I5. Atomic activation

### I6. Post-activation health

### I7. Rollback/release retention

### I8. Emergency admin mode

## Epic J — Quality and Guardrails

### J1. Static analysis strict baseline

### J2. Architecture rules

### J3. Forbidden APIs per layer

### J4. Contract suites

### J5. Browser tests

### J6. Visual regression

### J7. Change impact analysis

### J8. Branch protection/required checks

## Epic K — Reference Domain Validation

Dopiero po A–J:

- neutralny File Browser module,
- FileScope na local provider,
- następnie drugi use case z innym providerem,
- potwierdzenie, że module code nie zmienia się przy zmianie providera/theme.

## Suggested first implementation sequence

Najmniejszy pionowy slice po specification freeze:

```text
Bootstrap
→ Error handler
→ Manifest parser
→ Fixture module discovery
→ Module Dispatcher boundary
→ minimal PageDefinition
→ Base Theme minimal renderer
→ HTTP test pokazujący local failure isolation
```

Taki slice wcześniej weryfikuje najważniejszą tezę 1.0: uszkodzony dodatek nie zabija portalu.
