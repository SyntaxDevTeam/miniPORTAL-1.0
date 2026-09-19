# 10 — Testing, CI and Quality Gates

## 1. Cel

miniPORTAL 1.0 nie może polegać na zasadzie „programista/agent pamięta, żeby tego nie robić”. Najważniejsze reguły projektu mają być egzekwowane automatycznie.

Docelowym wejściem do lokalnej weryfikacji jest jedno polecenie:

```bash
composer verify
```

To samo logiczne verify uruchamia CI.

## 2. Piramida weryfikacji

```text
Fast/static
  ├ composer/schema validation
  ├ PHP lint
  ├ formatting/style
  ├ static analysis
  └ architecture dependency rules

Behavioral
  ├ unit
  ├ contract
  ├ integration
  └ migration tests

Runtime/UI
  ├ package preflight fixtures
  ├ HTTP smoke
  ├ Playwright
  ├ accessibility smoke
  └ visual regression
```

Szybkie testy powinny failować przed uruchomieniem kosztownych browser tests.

## 3. Composer verify — docelowy pipeline

Przykładowa struktura scripts:

```text
verify
├ validate
├ lint
├ analyse
├ architecture
├ test:unit
├ test:contract
├ test:integration
├ test:migrations
├ test:packages
└ test:ui
```

Konkretny tooling może zostać zmieniony ADR, ale funkcjonalne bramki pozostają wymagane.

## 4. Static analysis

Static analysis ma pracować na wysokim poziomie rygoru i wykrywać m.in.:

- niezgodne typy,
- unreachable/dead assumptions,
- brak obsługi nullable/resultów,
- niespójne interfaces,
- błędy generics/collections tam, gdzie tooling je obsługuje.

Nie obniżamy poziomu globalnie tylko po to, aby przejść CI. Wyjątki muszą być lokalne, opisane i możliwie czasowe.

## 5. Architecture tests

Architecture checks muszą kodować dependency rules z dokumentacji.

Przykładowe assertions:

```text
Core MUST NOT depend on Modules
UI MUST NOT depend on domain Modules
Modules MAY depend on Core/Contract
Modules MUST NOT depend on Core/Internal
Themes MUST NOT depend on Storage/Infrastructure
Modules MUST NOT directly use forbidden filesystem functions
```

Pierwszy wykonywalny baseline wykorzystuje `tools/architecture.php`, który analizuje tokeny PHP i granice katalogów. Guardrail jest częścią `composer verify`, a więc jest wykonywany lokalnie i w CI.

Baseline blokuje co najmniej:

- zależność Core od domenowych `Modules`,
- użycie wewnętrznego DI/registry/router internals przez publiczne Core Contracts,
- bezpośredni dostęp modułów do wewnętrznego container/router/package registry,
- bezpośrednie funkcje filesystem w warstwie modułów,
- zależności theme od package/module infrastructure.

Scanner ma pozostać mały i deterministyczny. Jeżeli zakres reguł przerośnie prostą analizę tokenów, ADR dopuszcza zastąpienie implementacji bez zmiany semantyki bramki `composer architecture`.

## 6. Forbidden API rules

Dla warstw, które muszą używać platformowych abstractions, CI powinno wykrywać bezpośrednie użycie funkcji/klas.

Przykład dla modułów korzystających z Filesystem API:

```text
file_get_contents
file_put_contents
unlink
rename
scandir
FilesystemIterator
RecursiveDirectoryIterator
```

Lista musi uwzględniać uzasadnione wyjątki w provider implementation. Reguła dotyczy layer, nie całego repo.

## 7. Contract tests

Każdy contract ma współdzielony test suite uruchamiany przeciw implementacjom.

### Filesystem providers

Ten sam zestaw testów przechodzi Local/SFTP/NodeAgent provider.

### Cache providers

Sprawdzamy TTL, key isolation, delete/invalidate, serialization rules i failure semantics.

### Theme

Każdy theme renderuje wspólny UI Catalog fixture.

### Module lifecycle

Fixture packages przechodzą te same discovery/preflight/activation rules co realne package.

## 8. Integration tests

Integration tests obejmują realne połączenie kilku warstw, np.:

```text
HTTP request
→ Router
→ permission
→ fixture module
→ filesystem scope
→ UI definition
→ renderer
→ HTML
```

Celem nie jest mockowanie wszystkiego, lecz potwierdzenie kontraktów na granicach.

## 9. Migration tests

Każda migracja powinna być sprawdzana co najmniej w scenariuszu:

- fresh install,
- upgrade from supported previous schema,
- idempotency/status ledger,
- failure behavior,
- rollback capability metadata.

Dla expand/contract testujemy okres kompatybilności dwóch wersji kodu, jeśli jest deklarowany.

Baseline migration engine posiada test integracyjny SQLite obejmujący plan bez
DDL, apply i ledger, ponowne idempotentne planowanie, checksum drift, usuniętą
definicję, błędny łańcuch wersji, preflight failure, politykę destructive/backup,
stary plan oraz brak wpisu ledger po błędzie wykonania. Provider produkcyjny
MySQL/MariaDB wymaga osobnej macierzy testów przed pierwszą migracją Core lub
pakietu używaną w wydaniu.

## 10. Package preflight fixtures

Repo powinno przechowywać kontrolowane wadliwe paczki/fixtures:

- bad JSON schema,
- invalid semver,
- missing capability,
- dependency cycle,
- syntax error,
- autoload failure,
- throw during registration,
- migration failure,
- runtime exception.

CI potwierdza, że system je odrzuca/izoluje zamiast się wysypywać globalnie.

## 11. UI Catalog tests

UI Catalog jest generowany automatycznie dla wszystkich publicznych komponentów i kluczowych stanów.

CI renderuje katalog dla:

- Base Theme,
- wszystkich theme będących częścią repo,
- desktop viewport,
- mobile viewport,
- opcjonalnie high contrast/reduced motion fixtures.

## 12. Visual regression

Browser runner wykonuje screenshoty ustalonych fixture i porównuje je z baseline.

Zmiana baseline jest jawna w PR. Agent nie powinien automatycznie akceptować nowych screenshotów tylko dlatego, że test nie przechodzi.

Visual diff ma wykrywać m.in.:

- przesunięty layout,
- brakujący komponent,
- overflow,
- niewidoczny focus,
- popsute modal overlays,
- niezamierzoną zmianę spacing/typografii.

## 13. Browser behavior tests

Playwright-like suite obejmuje:

- navigation/history,
- htmx fragment swap,
- form validation,
- modal/dialog,
- table search/sort/page,
- SSE reconnect behavior,
- auth redirect,
- permission denial,
- PWA shell/offline status.

## 14. Accessibility smoke

Automatyzacja nie zastąpi pełnego audytu, ale może wykrywać:

- brak label,
- obvious contrast issues,
- invalid aria relationships,
- keyboard trap,
- focus loss po dynamic swap.

## 15. Change impact analysis

CI powinno określać blast radius publicznych zmian.

Przykład:

```text
Changed: UI/Component/TableContract
Public API impact: YES
Affected themes: 5
Affected modules: 12
Required suites: ui-contract, themes, browser
```

Albo:

```text
Changed: FilesystemContract
Affected providers: local, sftp, node-agent
Affected modules: minecraft-files, vps-files, backups
```

## 16. Required checks na main

Docelowo branch protection wymaga co najmniej:

- static-analysis,
- architecture,
- unit-contract,
- integration,
- package-preflight,
- ui-contract,
- migration-check (jeśli dotyczy),
- browser/visual (jeśli dotyczy).

Nie każdy docs-only PR musi odpalać kosztowną pełną matrycę; CI może mieć path-aware selection, ale nie może pominąć relevant checks.

## 17. CI dla agentów AI

Agent może uruchomić lokalne verify, ale GitHub CI zawsze wykonuje własną niezależną weryfikację. Wynik deklarowany przez agenta nie jest źródłem prawdy.

## 18. Definition of green

„Build green” oznacza nie tylko brak syntax error. Oznacza przejście wszystkich bramek wymaganych przez impact analysis dla danego zakresu zmiany.
