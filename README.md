# miniPORTAL 1.0

miniPORTAL 1.0 to architektura-first przebudowa CMS/panelu SyntaxDevTeam. Celem nie jest „większa liczba funkcji”, lecz stworzenie stabilnego rdzenia, na którym funkcje można rozwijać niezależnie bez ryzyka, że pojedynczy moduł, szablon albo niepełna zmiana UI uszkodzi cały portal.

Projekt pozostaje świadomie lekki i server-driven: PHP generuje HTML, MySQL/MariaDB przechowuje dane, a nowoczesny frontend jest dokładany progresywnie poprzez htmx, natywny CSS, SSE, PWA i minimalny JavaScript. miniPORTAL ma zachowywać szybkość klasycznej aplikacji PHP, ale oferować interakcje kojarzone z nowoczesnymi SPA.

## Status

**Faza: implementacja UI Core / `1.0.0-alpha.3` (w toku).**

Core Kernel posiada bootstrap, bezpieczny error boundary, data-only package discovery, routing, capability registry i izolację błędu modułu. Service Platform ma bazowe kontrakty i providery dla cache, filesystemu, PDO Storage, migracji plan-first, HTTP, Jobs, Realtime i Audit. UI Core posiada semantyczne `PageDefinition`, renderer registry z dziedziczeniem theme, formularze, stany danych, podstawowy `DataTable`/`Pagination` oraz transport-neutralne renderowanie pełnej strony, regionu i pojedynczego komponentu. Registry pakietów może utrwalać release i wskaźnik aktywnej wersji w skonfigurowanej bazie po wykonaniu plan-first migracji `core.packages`. Ścieżka `ActiveModuleMount` publikuje trasy zaufanego modułu dopiero po udanym register/boot i izoluje błędy requestu. Nadal nie budujemy modułów domenowych takich jak Minecraft, VPS, PunisherX czy StableManagerX — najpierw platforma musi przejść kolejne kryteria roadmapy.

Panel `/admin` posiada ochronę sesyjną oraz logowanie przez GitHub, Google,
Microsoft i Discord. Core realizuje OAuth/OIDC `state`, PKCE i `nonce`, a
provider bazodanowy mapuje stabilne `provider:subject` na lokalne konta i role.
Na pustej instalacji pierwsza zweryfikowana tożsamość atomowo zostaje Ownerem;
po migracji istniejący Owner zachowuje przypisanie, a kolejne nowe konta
otrzymują stan `pending`. Logowanie rotuje identyfikator sesji, mutacje wymagają
CSRF, a sesje mają idle i absolute timeout. Panel `/admin/users` umożliwia przegląd i akceptację/blokowanie kont z kontrolą
uprawnień, CSRF i audytem. Pełny edytor permissions pozostaje kolejnym etapem
Security Core. Dane tożsamości ze starego VPS zostały jednorazowo przeniesione
do nowego schematu (12 kont i 16 tożsamości); szczegóły opisuje
[strategia migracji](docs/17-LEGACY-MIGRATION-STRATEGY.md).

## Obowiązkowy podział miniPORTAL 1.0

- **Core + manager modułów:** instalacja, aktualizacje, migracje, aktywacja,
  wyłączanie, usuwanie i rollback pakietów.
- **Niewyłączalne moduły systemowe:** wymagane funkcje platformy, np. obsługa
  szablonów, rozwijane jako wydzielone pakiety zgodne z Core.
- **Opcjonalne dodatki, także preinstalowane:** proste strony, artykuły,
  zarządzanie treścią/witryną, później zarządzanie serwerami Minecraft.
- **Widgety:** konfigurowalne instancje funkcji modułów osadzane w dowolnych
  deklarowanych punktach layoutu i treści strony przez UI API.
- **API i endpointy dla usług:** wersjonowane kontrakty integracji, centralna
  autoryzacja i powiązanie dostępności endpointu ze stanem modułu.

To wymagany model docelowy; obecna alpha realizuje tylko część fundamentów. Pierwszy wymagany moduł `system.themes` można jawnie aktywować przez `bin/miniportal packages:seed-system-themes`; aktywowany moduł udostępnia panel szablonów. Trwały registry i chroniony mount nie oznaczają jeszcze gotowego instalatora paczek: izolowany preflight kodu, bezpieczny loader dla dodatków, widgety i API usług pozostają do wykonania.
Szczegóły: [ADR-0012](docs/adr/0012-core-system-modules-addons-widgets-api.md).

## Zasady nadrzędne

1. **Błędy lokalne pozostają lokalne.** Awaria pojedynczego modułu nie może powodować HTTP 500 dla całego portalu.
2. **Core nie zna domeny.** Core nie wie czym jest Minecraft, PunisherX, VPS czy konkretny Node Agent.
3. **Moduły deklarują intencję, nie implementują infrastruktury.** Korzystają z publicznych kontraktów i capabilities.
4. **Biblioteki wykonują pracę.** Filesystem, cache, jobs, realtime, HTTP i inne usługi są współdzielone i testowane centralnie.
5. **UI opisuje semantykę.** Moduły tworzą PageDefinition i komponenty, a nie własny przypadkowy HTML/CSS.
6. **Theme decyduje jak pokazać UI, layout gdzie je umieścić.** Jeden komponent może wyglądać i układać się zupełnie inaczej w różnych szablonach.
7. **Base Theme implementuje 100% publicznego UI API.** Brak override w innym theme oznacza fallback, nigdy brak elementu.
8. **Reguły są egzekwowane maszynowo.** To, co da się sprawdzić automatycznie, nie może zależeć wyłącznie od pamięci programisty lub agenta AI.
9. **Aktualizacja nie oznacza aktywacji.** Nowy moduł/theme trafia do stagingu, przechodzi preflight i testy, a dopiero potem jest aktywowany atomowo.
10. **Nowoczesność bez ciężaru.** htmx, SSE, PWA, View Transitions i nowoczesny CSS są preferowane nad pełnym frameworkiem SPA, jeśli nie ma dla niego mierzalnej potrzeby.

## Docelowy model

```text
Request
  │
  ▼
HTTP Kernel / Router
  │
  ├── Auth / Permissions / Audit
  │
  ├── Module Dispatcher ──► Module
  │                         │
  │                         ├── Service Contracts / Capabilities
  │                         └── UI Definitions
  │
  └── UI Renderer
        │
        ├── Base Theme fallback
        ├── Active Theme overrides
        └── Selected Layout
              │
              ▼
            HTML
```

Warstwa usługowa jest niezależna od modułów:

```text
FilesystemContract
   ▲        ▲        ▲
   │        │        │
 Local     SFTP    NodeAgent
```

Moduł zależy od `filesystem` jako capability/contractu, a nie od konkretnego providera.

## Proponowany baseline technologiczny

- PHP 8.5+ z Composerem i PSR-4,
- wybierany podczas instalacji MySQL/MariaDB albo PostgreSQL jako storage relacyjny,
- APCu jako lekki cache lokalny i opcjonalny Redis dla większych wdrożeń,
- htmx 4.x jako warstwa hypermedia, ukryta za UI Core,
- natywny CSS: Grid, Container Queries, `:has()`, View Transitions, scroll-driven animations, design tokens,
- SSE jako domyślny transport server → browser dla danych live,
- WebSocket wyłącznie tam, gdzie potrzebny jest pełny duplex, np. PTY/terminal,
- PWA z application shell i Service Workerem, bez cache'owania wrażliwych danych administracyjnych,
- lazy loading ciężkich zależności frontendowych,
- opcjonalny edge/CDN dla assetów i publicznej części serwisu.

Dokładne wersje bibliotek będą przypinane przez Composer/lockfile i decyzje ADR przed rozpoczęciem implementacji.

## Konfiguracja runtime

Wspólny loader HTTP/CLI czyta opcjonalny plik `.env` w katalogu projektu, a
zmienne procesu mają nad nim pierwszeństwo. Punktem startowym jest
`.env.example`. Konfiguracja bazy jest opcjonalna dla minimalnego bootstrapu,
ale jeśli zostanie rozpoczęta, musi być kompletna. Obsługiwane wartości
`DATABASE_ENGINE` to `mysql`/`mariadb` oraz
`pgsql`/`postgresql`. Hasło pozostaje oddzielone od DSN.

## Dokumentacja

Pełny indeks znajduje się w [`docs/README.md`](docs/README.md).

Najważniejsze dokumenty startowe:

- [`ROADMAP.md`](ROADMAP.md) — kolejność realizacji miniPORTAL 1.0,
- [`AGENTS.md`](AGENTS.md) — obowiązkowe zasady dla agentów AI i zmian automatycznych,
- [`docs/01-ARCHITECTURE.md`](docs/01-ARCHITECTURE.md) — architektura całości,
- [`docs/03-MODULES-DEPENDENCIES-CAPABILITIES.md`](docs/03-MODULES-DEPENDENCIES-CAPABILITIES.md) — moduły i zależności,
- [`docs/05-UI-COMPONENT-MODEL.md`](docs/05-UI-COMPONENT-MODEL.md) — jedno źródło prawdy dla UI,
- [`docs/06-THEMES-LAYOUTS-DESIGN-SYSTEM.md`](docs/06-THEMES-LAYOUTS-DESIGN-SYSTEM.md) — theme/layout/component rendering,
- [`docs/08-RESILIENCE-UPDATE-ROLLBACK.md`](docs/08-RESILIENCE-UPDATE-ROLLBACK.md) — staging, preflight, izolacja i rollback,
- [`docs/10-TESTING-CI-QUALITY-GATES.md`](docs/10-TESTING-CI-QUALITY-GATES.md) — automatyczne bramki jakości,
- [`docs/11-AI-AGENT-GOVERNANCE.md`](docs/11-AI-AGENT-GOVERNANCE.md) — kontrola pracy agentów AI.

## Co nie jest celem pierwszych milestone'ów

- odtworzenie wszystkich funkcji obecnego miniPORTAL,
- natychmiastowa migracja istniejących modułów,
- stworzenie marketplace modułów,
- rozbudowany system pluginów domenowych,
- wybór „najładniejszego” theme,
- maksymalizacja liczby animacji,
- przepisywanie projektu na React/Vue/Next tylko dlatego, że są popularne.

Pierwszym dowodem poprawności architektury będzie możliwość wyrenderowania tej samej `PageDefinition` w dwóch radykalnie różnych layoutach/theme oraz bezpiecznego odrzucenia wadliwego modułu bez wpływu na Core.
