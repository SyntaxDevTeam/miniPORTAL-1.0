# Roadmap — miniPORTAL 1.0

Roadmapa jest celowo skoncentrowana na Core. Funkcje domenowe nie są priorytetem, dopóki platforma nie udowodni izolacji, kompatybilności UI i bezpiecznego lifecycle aktualizacji.

## Milestone 0 — Specification Freeze

**Cel:** zamknąć kontrakty architektoniczne przed produkcją dużej ilości kodu.

Zakres:

- zatwierdzenie terminologii: Core, Library, Capability, Provider, Module, UI Component, Renderer, Theme, Layout,
- zatwierdzenie granic zależności,
- wybór minimalnego baseline PHP/Composer,
- wybór formatu manifestów i wersjonowania contractów,
- wybór DI/router/migration tooling lub decyzja o lekkiej implementacji własnej,
- ADR dla htmx, SSE, PWA i cache,
- ustalenie Definition of Done.

**Exit criteria:** brak sprzeczności między dokumentami; otwarte decyzje mają właściciela/ADR i nie blokują rozpoczęcia Kernel.

## Milestone 1 — Core Kernel (`1.0.0-alpha.1`)

**Cel:** uruchamialny miniPORTAL bez modułów domenowych.

Zakres:

- bootstrap aplikacji,
- konfiguracja środowiska,
- DI/container,
- router,
- error handling,
- structured logging + correlation ID,
- event dispatcher,
- contract registry,
- module discovery bez wykonywania kodu modułu,
- manifest schema validation,
- podstawowy health/doctor command.

Test dowodowy:

- wadliwy manifest fixture jest odrzucany,
- Core nadal odpowiada poprawnie,
- błąd fixture module nie powoduje globalnego HTTP 500.

## Milestone 2 — Service Platform (`alpha.2`)

**Cel:** biblioteki usługowe gotowe do wykorzystania przez późniejsze moduły.

Zakres:

- CacheContract + Array/APCu provider,
- FilesystemContract + Local provider,
- Database/Storage contracts,
- HTTP client contract,
- Jobs contract,
- Realtime contract,
- Clock/ID abstractions tam, gdzie potrzebne do testów,
- audit/event hooks.

Test dowodowy Filesystem:

- scope root,
- path traversal denial,
- symlink escape denial,
- read/write/list/move/copy/delete,
- policy enforcement,
- błędy providera mapowane na stabilne wyjątki contractu.

## Milestone 3 — UI Core (`alpha.3`)

**Cel:** jeden semantyczny model strony niezależny od HTML konkretnego theme.

Zakres:

- PageDefinition,
- Component contract,
- Form API,
- Table API,
- navigation/breadcrumbs,
- alerts/notifications,
- dialogs/modals,
- loading/error/empty states,
- actions i intents,
- fragment rendering.

Test dowodowy:

- ta sama PageDefinition renderuje się bez zmian w module w co najmniej dwóch layoutach.

## Milestone 4 — Theme/Layout Engine (`alpha.4`)

**Cel:** pełne rozdzielenie semantyki UI od prezentacji.

Zakres:

- Base Theme z 100% pokryciem publicznego UI API,
- renderer registry,
- theme inheritance,
- layout registry,
- design tokens,
- assets pipeline,
- fallback resolver,
- Theme Contract Tests,
- UI Catalog.

Test dowodowy:

- theme nadpisuje tylko wybrane komponenty,
- wszystkie pozostałe pochodzą z Base Theme,
- brak override nie powoduje błędu,
- dwa theme mogą mieć zasadniczo różną strukturę layoutu.

## Milestone 5 — Modern Interaction Layer (`alpha.5`)

**Cel:** interaktywność SPA-like bez uzależniania modułów od konkretnego frameworka frontendowego.

Zakres:

- htmx adapter/renderer,
- partial navigation i fragment swaps,
- lazy loading,
- server-driven search/sort/filter/pagination,
- SSE transport,
- WebSocket adapter dla full-duplex,
- View Transitions,
- progressive enhancement,
- browser history/deep links,
- graceful no-JS baseline dla kluczowych ekranów tam, gdzie praktyczne.

## Milestone 6 — PWA, Cache, Performance (`beta.1`)

**Cel:** szybki portal na desktopie i urządzeniach słabszych/mobilnych.

Zakres:

- PWA manifest,
- Service Worker,
- application shell,
- offline status UI,
- zakaz cache'owania wrażliwych odpowiedzi,
- immutable hashed assets,
- fragment/object caching,
- APCu default,
- opcjonalny Redis provider,
- image optimization/lazy loading,
- budżety wydajnościowe.

## Milestone 7 — Safe Package Lifecycle (`beta.2`)

**Cel:** aktualizacja modułu/theme nie może ryzykować destabilizacji całego portalu.

Zakres:

- package quarantine/staging,
- integrity checks,
- schema validation,
- isolated PHP CLI preflight,
- dependency solver,
- migration plan/dry-run,
- smoke test,
- atomic activation,
- release history,
- rollback,
- module health,
- circuit breaker/auto-disable dla powtarzalnych awarii,
- panel diagnostyczny.

## Milestone 8 — Quality Gates and AI-safe Development (`beta.3`)

**Cel:** reguły architektury są egzekwowane przez narzędzia.

Zakres:

- `composer verify`,
- static analysis,
- dependency/architecture checks,
- public API compatibility checks,
- UI contract tests,
- visual regression tests,
- Playwright matrix desktop/mobile,
- change impact analysis,
- PR template,
- protected main + required checks,
- fixture modules/themes do testowania awarii i kompatybilności.

## Milestone 9 — First Domain Module (`rc.1`)

**Cel:** dopiero tutaj sprawdzamy platformę na realnym zastosowaniu.

Pierwszy moduł powinien być niewielki, ale dotykać kilku contractów. Kandydat: neutralny File Browser wykorzystujący Filesystem API, a nie od razu kompletna Konsola Minecraft.

Wymagania:

- zero bezpośredniej logiki filesystem w module,
- pełny FileScope,
- standardowy UI API,
- działa w co najmniej dwóch theme,
- awaria providera nie wyłącza Core,
- pakiet można zainstalować, odrzucić w preflight, aktywować i rollbackować.

## Milestone 10 — Migration Planning (`rc.2`)

Dopiero po udowodnieniu architektury:

- inwentaryzacja obecnego miniPORTAL,
- klasyfikacja funkcji: Core/Library/Module/obsolete,
- plan migracji danych,
- kompatybilność sesji/użytkowników,
- mapping starych permissions,
- kolejność przenoszenia modułów,
- okres równoległego działania, jeśli potrzebny.

## `1.0.0`

Wydanie 1.0 następuje dopiero, gdy:

- Core działa stabilnie bez modułów domenowych,
- lokalna awaria modułu pozostaje lokalna,
- staged update + rollback są potwierdzone testem integracyjnym,
- Base Theme ma pełne pokrycie UI contract,
- co najmniej dwa wyraźnie różne theme/layouty przechodzą contract tests,
- PWA nie przechowuje niebezpiecznych danych offline,
- `composer verify` jest wymaganym checkiem PR,
- realny moduł referencyjny działa wyłącznie przez publiczne kontrakty,
- dokumentacja architektury odpowiada implementacji.

## Po 1.0

Dopiero wtedy przewiduje się intensywny rozwój modułów domenowych, marketplace/repository dodatków, zewnętrzne SDK, podpisy pakietów, rozproszone workers, bardziej zaawansowane cache i kolejne integracje SyntaxDevTeam.