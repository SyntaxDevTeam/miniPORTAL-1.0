# 18 — Definition of Done and Risk Register

## 1. Definition of Done dla zmiany

Zmiana jest ukończona, gdy:

- implementuje wymaganie bez obchodzenia contracts,
- posiada odpowiedni test,
- relevant static/architecture checks przechodzą,
- error path jest obsłużony,
- security/cache impact został oceniony,
- public API compatibility została oceniona,
- docs są aktualne,
- `composer verify` przechodzi w wymaganym zakresie.

## 2. Definition of Done dla publicznego UI component

- public contract,
- Base Theme renderer,
- responsive behavior,
- loading/error/disabled states jeśli dotyczą,
- keyboard/focus semantics,
- UI Catalog fixture,
- Theme Contract Test coverage,
- visual regression baseline.

## 3. Definition of Done dla providera

- implementuje capability contract,
- przechodzi shared contract suite,
- ma timeout/error mapping,
- health check,
- telemetry,
- security model,
- deklaruje unsupported operations/capabilities.

## 4. Definition of Done dla modułu

- manifest schema valid,
- dependencies jawne,
- brak imports internals,
- permissions deklarowane centralnie,
- nie duplikuje libraries,
- UI przez publiczne components,
- package preflight pass,
- runtime failure test,
- disable/uninstall behavior zdefiniowany,
- docs/help metadata.

## 5. Definition of Done dla theme

- manifest valid,
- UI API compatible,
- required layout roles,
- renderer overrides nie łamią semantics,
- Base fallback działa,
- UI Catalog desktop/mobile pass,
- browser/accessibility smoke pass,
- brak globalnego crash po problemie theme (emergency Base fallback).

## 6. Definition of Done dla release

- required CI green,
- compatibility check,
- migrations plan/test,
- artifact/checksum,
- changelog,
- activation test,
- rollback/recovery test odpowiedni do ryzyka,
- docs version aligned.

# Risk Register

## R-001 — Fałszywe poczucie izolacji PHP in-process

**Ryzyko:** try/catch nie chroni przed każdym process-level failure.

**Mitigacja:** isolated preflight, mały trusted surface, możliwość worker mode, jasne dokumentowanie gwarancji.

## R-002 — SSE + klasyczny PHP-FPM zużywa workery

**Ryzyko:** długie połączenia mogą zablokować zbyt wiele workerów.

**Mitigacja:** ADR deployment realtime, osobna warstwa/process jeśli pomiary wykażą potrzebę; nie projektować transportu tak, aby module znał runtime.

## R-003 — Theme flexibility utrudni stabilność DOM

**Ryzyko:** radykalnie różne renderery mogą zwiększyć koszt testów i accessibility.

**Mitigacja:** semantic component contract, Theme Contract Suite, UI Catalog, ograniczenie gwarancji do zachowania zamiast identycznego DOM.

## R-004 — Zbyt rozbudowane UI abstraction

**Ryzyko:** stworzenie własnego „frameworka UI” większego niż problem.

**Mitigacja:** komponenty dodawane przez realne use cases, małe contracts, escape hatch tylko dla custom domain component z fallback rendererem.

## R-005 — Dependency resolver complexity

**Ryzyko:** zbyt zaawansowany solver opóźni Core.

**Mitigacja:** ograniczony model SemVer/capabilities w 1.0; nie odtwarzać całego Composera dla runtime package graph bez potrzeby.

## R-006 — Rollback po migracji DB

**Ryzyko:** kod można cofnąć, schema nie zawsze.

**Mitigacja:** expand/contract, reversible metadata, backup prerequisite, blokada fałszywego auto-rollbacku.

## R-007 — Service Worker przecieka dane

**Ryzyko:** zbyt szeroka strategia cache zapisze prywatne odpowiedzi.

**Mitigacja:** allowlist caching, no-store sensitive defaults, security tests.

## R-008 — AI obchodzi abstractions

**Ryzyko:** szybkie implementacje tworzą lokalne helpery i niespójność theme.

**Mitigacja:** AGENTS, architecture rules, forbidden APIs, Base Theme fallback, PR scope, required CI.

## R-009 — Overengineering przed pierwszym use case

**Ryzyko:** zaprojektowanie wielu abstractions bez walidacji.

**Mitigacja:** fixture-driven Core, następnie mały reference File Browser module przed migracją dużych funkcji.

## R-010 — Cache invalidation i permissions

**Ryzyko:** użytkownik zobaczy dane z nieaktualnym permission context.

**Mitigacja:** conservative defaults, user/resource-aware keys, invalidation on privilege changes, no shared caching sensitive HTML.

## R-011 — Remote provider latency

**Ryzyko:** powolny Node Agent/SFTP blokuje cały dashboard.

**Mitigacja:** lazy components, timeout, degraded states, telemetry, bounded retry/circuit breaker.

## R-012 — Core staje się „god object”

**Ryzyko:** wszystko trafia do Core dla wygody.

**Mitigacja:** library-first review: jeżeli funkcja może działać jako neutralna capability, nie trafia do Kernel.

## R-013 — Base Theme staje się nieutrzymywalny

**Ryzyko:** zbyt wiele specjalistycznych komponentów w publicznym UI API.

**Mitigacja:** publiczny component tylko dla generycznych wzorców; domain-specific component ma renderer w module i optional theme override.

## R-014 — Zbyt duży initial frontend

**Ryzyko:** kolejne funkcje dokładają globalny JS/CSS.

**Mitigacja:** performance budgets, bundle analysis, lazy heavy libs, CSS/component ownership.

## 7. Risk review cadence

Przed wejściem w każdy główny milestone należy przejrzeć risk register i dodać ryzyka ujawnione przez implementację. Ryzyko z wysokim wpływem bez planu mitigacji blokuje stabilne 1.0.