# 01 — Architecture

## 1. Model warstw

miniPORTAL 1.0 używa architektury warstwowej z kierunkiem zależności do środka/publicznych kontraktów.

```text
┌────────────────────────────────────────────────────┐
│                 Domain Modules                     │
│ Minecraft / VPS / integrations / future modules   │
└──────────────────────┬─────────────────────────────┘
                       │ public contracts only
┌──────────────────────▼─────────────────────────────┐
│                    UI API                          │
│ PageDefinition / Components / Forms / Tables       │
└──────────────────────┬─────────────────────────────┘
                       │
┌──────────────────────▼─────────────────────────────┐
│              Service Contracts                    │
│ FS / Cache / DB / Jobs / HTTP / Realtime / Audit  │
└──────────────────────┬─────────────────────────────┘
                       │
┌──────────────────────▼─────────────────────────────┐
│                  Core Kernel                       │
│ bootstrap / router / DI / modules / auth / errors │
└────────────────────────────────────────────────────┘

Outside public contracts:
Providers, Infrastructure Implementations, Theme Renderers
```

Kierunek zależności nie oznacza, że UI jest „ważniejsze” niż usługi. Oznacza tylko, że moduły nie mogą omijać publicznych warstw.

## 2. Główne bounded contexts techniczne

### Core Kernel

Odpowiada za życie aplikacji, nie za funkcje domenowe.

### Service Platform

Zapewnia generyczne capabilities. Contract i provider są rozdzielone.

### Module Platform

Odpowiada za discovery, dependency resolution, lifecycle i runtime boundary.

### UI Platform

Przechowuje semantykę interfejsu niezależnie od HTML theme.

### Theme Platform

Renderuje UI oraz dostarcza layouty i design tokens.

### Delivery/Frontend

HTTP, htmx, SSE, WebSocket, PWA, asset delivery.

## 3. Fundamentalne dependency rules

### Core

Core może zależeć wyłącznie od własnych abstractions i wybranych stabilnych bibliotek technicznych. Core nie zależy od modułów domenowych.

### Libraries

Library contract nie powinien zależeć od konkretnego modułu. Provider może zależeć od infrastruktury potrzebnej do implementacji, np. klienta SFTP.

### Modules

Module może zależeć od:

- Core public API,
- UI public API,
- Library contracts,
- capabilities zadeklarowanych w manifeście.

Module nie może zależeć od:

- `Core/Internal`,
- plików template aktywnego theme,
- implementacji konkretnego providera bez jawnej potrzeby,
- globalnego stanu innego modułu bez kontraktu.

### Themes

Theme zależy od UI rendering contract i assets platform. Theme nie zna logiki biznesowej.

## 4. Composition Root

Jedynym miejscem, które zna konkretne implementacje i składa aplikację, jest Composition Root podczas bootstrapu.

Przykład:

```text
FilesystemContract → LocalFilesystemProvider
CacheContract      → ApcuCacheProvider
RealtimeContract   → SseRealtimeProvider
```

Moduł otrzymuje `FilesystemContract`, a nie tworzy `LocalFilesystemProvider` samodzielnie.

## 5. Capability Registry

Niektóre zależności są bardziej naturalne jako capability niż konkretna paczka.

Przykład:

```text
capability: filesystem@1
providers:
- local-filesystem
- sftp-filesystem
- node-agent-filesystem
```

Dependency resolver wybiera provider zgodny z wymaganiami środowiska/konfiguracji.

Capability powinna mieć stabilny contract i wersję API niezależną od wersji konkretnego providera.

## 6. Request flow

```text
Browser
  ↓
Front Controller
  ↓
Request Context
  ├ correlation ID
  ├ authenticated principal
  ├ locale
  └ request metadata
  ↓
Router
  ↓
AuthN/AuthZ middleware
  ↓
Core route OR Module Dispatcher
  ↓
Use Case / Controller
  ↓
Service Contracts
  ↓
PageDefinition / Result
  ↓
UI Renderer
  ↓
Theme + Layout
  ↓
HTTP Response
```

Dla htmx renderer może wybrać fragment zamiast pełnego layoutu, ale semantyka strony pozostaje ta sama.

## 7. Runtime boundary modułu

Każdy request wchodzący do modułu przechodzi przez Module Dispatcher.

Dispatcher odpowiada za:

- sprawdzenie, czy moduł jest aktywny i healthy,
- context i permissions,
- boundary `Throwable` dla błędów aplikacyjnych,
- time/error metrics,
- correlation ID,
- mapowanie błędu na bezpieczny modułowy error state,
- opcjonalny circuit breaker.

Nie wszystkie awarie procesu PHP są możliwe do izolowania in-process. Dlatego architektura przewiduje możliwość uruchamiania wybranych/niezaufanych zadań w osobnym workerze w przyszłości.

## 8. Dane i storage

Core posiada tylko dane Core. Moduły posiadają własny logiczny namespace danych/migracji.

Należy unikać sytuacji, w której jeden moduł odczytuje prywatne tabele innego modułu bez publicznego API.

Rekomendowany model:

```text
core_*              → dane Core
module_<id>_*       → dane modułu
migration ledger    → oddzielny per owner/package
```

Dokładna konwencja nazw zostanie zatwierdzona ADR.

## 9. Architektura katalogów — propozycja

```text
miniPORTAL/
├── src/
│   ├── Core/
│   │   ├── Application/
│   │   ├── Contract/
│   │   ├── Error/
│   │   ├── Module/
│   │   ├── Routing/
│   │   └── Security/
│   ├── UI/
│   │   ├── Contract/
│   │   ├── Component/
│   │   ├── Form/
│   │   ├── Table/
│   │   └── Rendering/
│   └── Infrastructure/
├── libraries/
├── providers/
├── themes/
│   └── base/
├── modules/
├── public/
├── bin/
├── tests/
│   ├── Unit/
│   ├── Integration/
│   ├── Contract/
│   ├── Architecture/
│   └── Browser/
└── docs/
```

To struktura docelowa, nie obowiązek implementacyjny przed ADR.

## 10. Architektura jako kod

Dependency rules muszą być sprawdzane automatycznie. Przykładowe reguły:

```text
Modules         -> may use Core/Contract
Modules         -> may use UI/Contract
Modules         -> must not use Core/Internal
Themes          -> may use UI/Rendering
Themes          -> must not use Storage
UI              -> must not use domain modules
Core            -> must not use domain modules
```

CI powinno analizować graph namespace/importów i odrzucać naruszenia.

## 11. Stabilne kontrakty, wymienne implementacje

Każdy ważny subsystem powinien mieć:

- publiczny contract,
- jedną implementację referencyjną,
- test suite contractu możliwy do uruchomienia przeciw dowolnemu providerowi,
- dokumentację błędów i zachowania,
- wersję API.

To pozwala wymienić np. cache APCu na Redis bez zmiany modułów.