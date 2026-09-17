# 02 — Core Kernel

## 1. Odpowiedzialność Core

Core ma być mały, przewidywalny i domenowo neutralny. Jeżeli funkcja może istnieć jako niezależna biblioteka lub moduł, nie powinna automatycznie trafiać do Kernel.

Core odpowiada za:

- bootstrap,
- konfigurację środowiska,
- composition root/DI,
- routing,
- request context,
- sessions/authentication hooks,
- authorization/permission engine,
- module discovery i lifecycle orchestration,
- error handling,
- structured logging,
- event dispatching,
- contract/capability registry,
- package activation metadata,
- health/diagnostics,
- UI bootstrap i theme selection.

Core nie odpowiada za:

- pliki Minecraft,
- backupy Minecraft,
- terminal VPS,
- PunisherX,
- konkretny file browser,
- konkretnego providera SFTP,
- layout konkretnego theme.

## 2. Bootstrap

Proponowana sekwencja:

```text
1. Environment bootstrap
2. Minimal error handler
3. Read Core config
4. Composer autoload
5. Build base container
6. Register Core contracts
7. Discover package manifests (data only)
8. Validate dependency graph
9. Register approved providers/modules
10. Select theme
11. Build router
12. Handle request
```

Kluczowa zasada: **discovery manifestów nie wykonuje entrypointów modułów**.

## 3. Minimalny error handler przed modułami

Core error handler musi działać zanim załadowany zostanie jakikolwiek moduł. Ma zapewniać:

- correlation ID,
- bezpieczny ekran błędu,
- log techniczny,
- brak stack trace dla użytkownika produkcyjnego,
- rozróżnienie CoreFailure / ModuleFailure / ProviderFailure / ValidationFailure.

## 4. Configuration

Konfiguracja powinna posiadać jawny schema/model zamiast rozproszonego czytania `$_ENV` i plików config.

Warstwy config:

```text
Defaults
  ↓
Environment config
  ↓
Installation config
  ↓
Runtime/admin-configurable values (tylko te, które są bezpieczne)
```

Sekrety nie mogą być przechowywane w konfiguracji eksportowanej do UI ani logach.

## 5. RequestContext

Request powinien otrzymywać niemutowalny lub kontrolowanie mutowalny context zawierający m.in.:

```text
requestId/correlationId
principal/userId
locale
timezone
client hints
permissions snapshot/reference
CSRF context
feature/capability context
```

Moduły nie powinny bezpośrednio opierać logiki na globalach PHP.

## 6. Router

Router ma obsługiwać:

- Core routes,
- namespaced module routes,
- route requirements,
- middleware chain,
- route names,
- generation URL,
- pełne i fragmentowe odpowiedzi UI.

Moduł rejestruje route poprzez publiczne API. Nie modyfikuje globalnej tablicy routingu.

## 7. Event system

Eventy służą do luźnego powiązania reakcji, nie do ukrywania krytycznych zależności.

Zasady:

- event nazwany i wersjonowany,
- payload oparty o DTO/contract,
- krytyczna funkcja nie może wymagać niejawnego listenera bez deklaracji,
- błędy listenerów muszą być izolowane/logowane zgodnie z polityką eventu,
- event biznesowy ≠ command/request.

## 8. Core health

Core powinien udostępniać health status co najmniej dla:

- DB connectivity,
- cache provider,
- active theme,
- filesystem temp/storage,
- module registry,
- migration state,
- background worker/jobs,
- realtime transport.

Stan health nie powinien ujawniać wrażliwych szczegółów anonimowemu użytkownikowi.

## 9. CLI `bin/miniportal`

Od początku należy przewidzieć CLI do operacji administracyjnych i CI.

Docelowe komendy:

```text
miniportal doctor
miniportal packages:list
miniportal package:preflight <path>
miniportal package:activate <id> <version>
miniportal package:rollback <id>
miniportal migrations:plan
miniportal migrations:status
miniportal cache:clear
miniportal ui:catalog
miniportal verify-runtime
```

Web UI powinno korzystać z tych samych usług aplikacyjnych, a nie posiadać osobnej logiki wdrażania.

## 10. Error taxonomy

Proponowane stabilne kategorie:

- `ValidationError` — dane/manifest niezgodny ze schematem,
- `DependencyError` — brak/niewłaściwa wersja dependency,
- `PermissionDenied`,
- `NotFound`,
- `Conflict`,
- `ProviderUnavailable`,
- `ModuleUnavailable`,
- `MigrationBlocked`,
- `CoreFailure`.

UI mapuje kategorię na komunikat; log zachowuje pełny exception chain.

## 11. Feature flags i experimental API

Core może wspierać feature flags, ale nie mogą one zastępować wersjonowania API.

Eksperymentalny contract musi być jawnie oznaczony i nie powinien być używany przez stabilne moduły bez akceptacji ryzyka.

## 12. Kryteria gotowości Kernel

Kernel jest gotowy do kolejnego milestone'u, gdy:

- uruchamia stronę Core bez modułów,
- potrafi wykryć i zwalidować manifest fixture,
- odrzuca niepoprawny dependency graph bez crasha,
- ma error handler przed załadowaniem modułów,
- `doctor` raportuje stan środowiska,
- architecture tests potwierdzają brak domenowych zależności w Core.