# 03 — Modules, Dependencies and Capabilities

## 1. Definicja modułu

Module to rozszerzenie funkcjonalne miniPORTAL, które składa istniejące contracts/capabilities oraz może dodawać własną logikę domenową, routes, permissions, storage i UI definitions.

Module nie jest autonomiczną kopią frameworka.

Dobre moduły są cienkie tam, gdzie problem jest infrastrukturalny, i bogate tam, gdzie problem jest domenowy.

Przykład:

```text
Minecraft Files Module
  ├ defines scope/root/policy
  ├ defines permissions
  ├ maps server identity → filesystem capability
  └ composes FileBrowser UI

Filesystem Library
  └ performs actual file operations
```

## 1a. Moduł systemowy a opcjonalny dodatek

Moduły systemowe są wymaganymi, niewyłączalnymi pakietami zgodnego zestawu Core;
przykładem jest obsługa szablonów. Zaufana dystrybucja ustala ten zestaw, nie
samodeklaracja zewnętrznego pakietu. Disable/uninstall musi być odrzucane przez
manager niezależnie od wejścia (UI/CLI/API). Aktualizacja wymaga preflightu
zgodnego zestawu; awaria uruchamia degraded/recovery zamiast zwykłego auto-disable.

Dodatki są opcjonalne również wtedy, gdy profil instalacji dostarcza je od razu
(np. Strony, Artykuły). Disable zachowuje dane i konfigurację widgetów, ale
zatrzymuje wkłady UI, endpointy i jobs. Uninstall i osobne purge wymagają planu
zależności. Poniższe reguły auto-disable/uninstall dotyczą dodatków; nie
uprawniają do wyłączenia wymaganego modułu systemowego.

Widgety oraz endpointy są wkładami modułu, a nie autonomicznymi bootstrapami.
Docelowy model opisuje [ADR-0012](adr/0012-core-system-modules-addons-widgets-api.md);
aktualny manifest nie implementuje jeszcze rozróżnienia polityk system/dodatek.

## 2. Package manifest

Manifest jest czystymi danymi i może być analizowany bez wykonania kodu.

Przykład konceptualny:

```json
{
  "schema": 1,
  "id": "minecraft-console",
  "name": "Minecraft Console",
  "version": "1.4.0",
  "type": "module",
  "requires": {
    "core": "^1.0",
    "capabilities": {
      "filesystem": "^1.0",
      "realtime": "^1.0",
      "jobs": "^1.0"
    }
  },
  "entrypoint": "SyntaxDevTeam\\MiniPortal\\Minecraft\\MinecraftModule"
}
```

Manifest powinien przechodzić JSON Schema validation przed autoload entrypointu.

## 3. Discovery bez wykonania

Core skanuje wyłącznie manifesty package directories/release registry. Nie wykonuje `require module.php` tylko po to, aby dowiedzieć się jaka jest wersja modułu.

Sekwencja:

```text
package found
  ↓
read manifest
  ↓
schema validate
  ↓
semver validate
  ↓
dependency graph validate
  ↓
preflight candidate
  ↓
entrypoint may be loaded
```

## 4. Dependency types

### Core dependency

Określa kompatybilny publiczny Core API.

### Capability dependency

Preferowany mechanizm dla infrastruktury.

```text
requires filesystem@^1
```

nie:

```text
requires NodeAgentFilesystemProvider
```

### Module dependency

Dozwolona tylko wtedy, gdy funkcja semantycznie zależy od innego modułu. Musi być jawna.

### Optional dependency

Moduł może rozszerzyć zachowanie, gdy capability/module jest dostępny, ale brak nie może uniemożliwić startu.

## 5. Dependency resolver

Resolver wykonuje:

- semver parsing,
- graph validation,
- cycle detection,
- required vs optional dependencies,
- provider capability resolution,
- conflicts,
- minimal compatibility report.

Przy błędzie generuje raport danych, nie exception wychodzący do globalnego bootstrapa.

Przykład:

```text
Package rejected: minecraft-console 1.8.0

Missing capability:
  filesystem ^2.0

Available:
  filesystem 1.6 provided by local-filesystem 1.9.2

Core remains operational.
```

## 6. Module lifecycle

Proponowany lifecycle:

```text
DISCOVERED
  ↓
VALIDATED
  ↓
STAGED
  ↓
PREFLIGHT_PASSED
  ↓
READY
  ↓
ACTIVE
  ↘
   DEGRADED
  ↘
   DISABLED
  ↘
   FAILED
```

`INSTALLED` nie powinno być synonimem `ACTIVE`.

## 7. Entry point contract

Przykład ideowy:

```php
interface Module
{
    public function register(ModuleRegistration $registration): void;
    public function boot(ModuleContext $context): void;
    public function health(): ModuleHealth;
}
```

`register()` powinno deklarować rzeczy, a nie wykonywać ciężkie I/O.

Możliwe deklaracje:

- routes,
- permissions,
- services,
- migrations,
- UI navigation contributions,
- events/subscriptions.

`boot()` uruchamia moduł dopiero po pomyślnym rozwiązaniu zależności.

## 8. Namespacing zasobów

Każdy moduł ma własny namespace dla:

- routes,
- permissions,
- translations,
- config,
- storage/migrations,
- cache keys,
- jobs,
- events publikowanych jako owner.

To zmniejsza kolizje i ułatwia uninstall/diagnostics.

## 9. Permissions modułu

Module deklaruje permission definitions, ale enforcement wykonuje Core Security.

Przykład:

```text
minecraft.files.view
minecraft.files.read
minecraft.files.write
minecraft.files.delete
```

Module nie implementuje własnego systemu ról.

## 10. Runtime boundary

Każde wywołanie modułu przechodzi przez Module Dispatcher. Dispatcher jest odpowiedzialny za:

- health check/circuit breaker,
- error mapping,
- telemetry,
- permission context,
- transaction/context cleanup.

Błąd modułu może zwrócić modułowy ErrorComponent, ale nie może wyłączyć routingu Core.

## 11. Auto-disable/circuit breaker

Dla powtarzających się błędów można przewidzieć politykę:

```text
N errors / T seconds
  ↓
module state = DEGRADED
  ↓
optional cooldown/retry
  ↓
if continues → DISABLED
```

Mechanizm musi rozróżniać błędy zależnego providera od błędów samego modułu, aby diagnoza była uczciwa.

## 12. Uninstall

Uninstall to osobny lifecycle niż disable.

Disable:

- nie usuwa danych,
- zatrzymuje routes/jobs/UI contributions.

Uninstall może:

- usunąć pakiet,
- pozostawić dane jako orphaned archive,
- opcjonalnie wykonać explicit purge po potwierdzeniu.

Domyślnie uninstall nie powinien destrukcyjnie usuwać danych bez jawnej decyzji administratora.

## 13. Fixture modules

Core repo powinno posiadać testowe moduły:

- `fixture-good`,
- `fixture-bad-manifest`,
- `fixture-missing-dependency`,
- `fixture-throws-on-boot`,
- `fixture-runtime-error`,
- `fixture-cycle-a/b`,
- `fixture-migration-failure`.

Są one ważniejsze dla jakości platformy niż szybkie przenoszenie realnych modułów.

## 14. Implementowany baseline Core alpha

Od etapu Core Kernel obowiązuje wykonywalny model:

- release pakietu ma własny stan lifecycle,
- active release jest osobnym pointerem registry i nie wynika z samej obecności plików,
- przejścia między stanami są walidowane przez `PackageLifecycle`,
- preflight składa się z niezależnych checków i daje `PackagePreflightReport`,
- nieudany preflight przechodzi do `FAILED_PREFLIGHT`, a nie do `ACTIVE`,
- `Module::register()` działa przed `boot()`,
- route contributions są buforowane i commitowane do Routera atomowo,
- nazwy oraz ścieżki tras modułu są namespacowane przez Core,
- eventy Core/module integration muszą mieć jawny contract name i integer contract version.

`InMemoryPackageRegistry` pozostaje implementacją testową/bootstrappingową. Po migracji `core.packages` skonfigurowana baza zapewnia `DatabasePackageRegistry`: utrwala release i oddzielny wskaźnik aktywnej wersji. Zmiany stanu używają sprawdzenia poprzedniego stanu, a zmiana stanu i wskaźnika aktywnej wersji odbywa się w jednej transakcji. Nieudane przełączenie pozostawia poprzedni stan release i pointer. Samo zapisanie release jako `ACTIVE` nie powoduje jego uruchomienia bez aktywnego wskaźnika.

`RequiredPackagePolicy` przyjmuje listę ID wyłącznie z zaufanej dystrybucji Core, nie z manifestu. Odmawia przejścia wymaganego aktywnego/gotowego pakietu do `DISABLED` lub `FAILED`; `DEGRADED` pozostaje dostępny do diagnostyki. Lista produkcyjna zawiera `system.themes`. Manager blokuje również uninstall tego wydania.

`ActiveModuleMount` sprawdza aktywny wskaźnik, buforuje deklaracje tras, wykonuje boot i dopiero wtedy publikuje trasy. Przed każdym requestem sprawdza nadal aktywną wersję; wyjątki mapuje na lokalną odpowiedź z ID. `ActiveModuleLoader` wczytuje aktywne wydanie z katalogu przypisanego w registry i montuje moduły w kolejności zależności. `ReviewedPackageInstaller` kopiuje tylko kod przejrzany w repozytorium do niemutowalnego katalogu release; jego preflight nie jest sandboxem dla obcych archiwów. Q-006 pozostaje otwarte dla atomowego zestawu Core + moduły systemowe.

## Odczyt inwentarza pakietów

`PackageRegistry::allReleases()` zwraca wszystkie zarejestrowane wydania,
posortowane po identyfikatorze pakietu i wersji. To addytywne rozszerzenie
kontraktu (minor); adaptery registry muszą je zaimplementować. Panel
`/admin/modules` używa wyłącznie tego publicznego kontraktu oraz
`active(id)`, pokazuje stan i politykę wymaganych pakietów. Jest na razie
widokiem diagnostycznym. Instalacja/aktualizacja, aktywacja i wyłączenie
działają przez planowane operacje CLI. Panel nie udostępnia jeszcze tych mutacji;
przeniesienie ich do UI wymaga tej samej kontroli planu, CSRF i uprawnień.
