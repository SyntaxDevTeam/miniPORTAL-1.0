# 12 — Performance, Cache and Observability

## 1. Wydajność jako właściwość architektury

miniPORTAL ma być lekki przede wszystkim poprzez ograniczenie niepotrzebnej pracy, a nie przez mikrooptymalizacje.

Priorytety:

- mniej kodu JS na initial load,
- brak pobierania ciężkich bibliotek bez potrzeby,
- cache odpowiednich warstw,
- lazy rendering danych,
- unikanie agresywnego pollingu,
- ograniczanie powtarzalnych DB/remote calls.

## 2. Warstwy cache

```text
Browser immutable assets
        ↓
Optional edge/CDN public cache
        ↓
PHP OPcache
        ↓
Fragment/application cache
        ↓
Object/query cache
        ↓
Database / remote provider
```

Każda warstwa ma inną odpowiedzialność i privacy model.

## 3. Asset cache

Hashed assets:

```text
app.<hash>.css
ui.<hash>.js
logo.<hash>.svg
```

mogą otrzymać długi `max-age` i `immutable`, bo zmiana treści zmienia URL.

## 4. Dynamic admin cache

Sensitive/spersonalizowany HTML domyślnie nie trafia do shared cache.

Cache w aplikacji może przechowywać bezpieczne dane/fragmenty, ale key musi uwzględniać wszystko, co wpływa na wynik, np. resource, locale, permissions variant, package version.

Nie wolno cache'ować wyniku autoryzacji w sposób pozwalający użyć starego uprawnienia po jego odebraniu bez jawnej polityki TTL/invalidation.

## 5. CacheContract

Module nie zna APCu/Redis bezpośrednio.

Aktualny contract:

```text
Cache
├ get
├ has
├ set(value, ttl?)
├ remember(producer, ttl?)
├ delete
└ scope(namespace)
```

Implementacje:

- Array/Null dla testów i fallbacku,
- APCu jako preferowany provider dla single-node,
- Redis pozostaje opcjonalnym przyszłym providerem dla multi-process/multi-node lub zaawansowanych use cases.

`ttl <= 0` oznacza brak retencji/usunięcie wpisu. Brak TTL oznacza retencję zgodną z providerem bez wymuszonego czasu wygaśnięcia.

## 6. Namespace cache

Każdy package powinien otrzymywać namespaced cache scope zamiast ręcznie budować globalne klucze. Scope składa prefiks po stronie biblioteki i izoluje logicznie klucze pakietów.

Pierwszy baseline nie implementuje jeszcze masowego invalidowania tagów/namespace. Ta funkcja zostanie dodana dopiero, gdy activation/release cache invalidation będzie miało konkretny use case i jednoznaczną semantykę między APCu/Redis.

## 7. Fragment cache

Renderer może cache'ować bezpieczne, deterministyczne fragmenty, ale decyzja musi uwzględniać:

- principal/permissions,
- locale,
- active theme,
- layout variant,
- package version,
- input data version.

Nie należy wprowadzać fragment cache zanim nie ma jasnego cache key model.

## 8. Database performance

Zasady:

- query count telemetry w dev/test,
- unikanie N+1,
- pagination server-side dla dużych tabel,
- indeksy wynikające z realnych query patterns,
- limit search complexity,
- batch operations tam, gdzie naturalne.

## 9. Remote providers

SFTP/Node Agent/remote APIs wymagają:

- connect/request timeout,
- bounded retries,
- backoff,
- circuit breaker tam, gdzie warto,
- cancellation/time budget,
- telemetry latency.

Nie należy blokować całej strony na jednym powolnym widżecie. Komponent może lazy-loadować i pokazać degraded state.

## 10. Realtime vs polling

Jeżeli serwer generuje aktualizacje, preferujemy SSE zamiast requestu co sekundę. Polling może pozostać fallbackiem dla wybranych środowisk, z rozsądnym interval/backoff.

## 11. Performance budgets

Budżety ustalamy na rzeczywistym profilu urządzenia i sieci. W CI można śledzić regresje:

- base bundle size,
- CSS size,
- number of requests,
- page render timings,
- browser Web Vitals-like metrics,
- server response latency dla fixture dashboard.

## 12. Structured logging

Każdy log powinien móc zawierać:

```text
level
timestamp
message
correlation_id
package_id
module_id
user_id (jeśli bezpieczne)
route
error_code
context
```

Nie logujemy secretów ani pełnych wrażliwych payloadów.

## 13. Correlation ID

Każdy request otrzymuje ID propagowane przez:

- Core,
- Module Dispatcher,
- service providers,
- remote agent request tam, gdzie protokół pozwala,
- audit/log entries.

UI błędu może pokazać np. `Error ID MP-...`, dzięki czemu admin odnajduje szczegół bez eksponowania stack trace.

## 14. Metrics

Minimalne metryki platformy:

- request count/latency/error,
- module errors/health,
- provider latency/error,
- DB query latency,
- cache hit/miss,
- job queue/run/failure,
- SSE connections/reconnects,
- package activation result.

Format/eksporter jest wymienny; moduły emitują poprzez telemetry contract.

## 15. Diagnostics UI

Admin panel diagnostyczny powinien pokazywać:

- Core version,
- PHP/environment,
- DB status,
- active cache provider,
- package states,
- dependency issues,
- active theme/fallback state,
- migration status,
- realtime/jobs status,
- ostatnie correlation IDs/błędy w bezpiecznej formie.

## 16. Profiling development-only

W dev możliwe są:

- query profiler,
- render/component timings,
- cache trace,
- dependency graph,
- request timeline.

Nie należy wystawiać tych danych publicznie na produkcji.

## 17. Zasada optymalizacji

Najpierw pomiar, potem optymalizacja. Wyjątek stanowią oczywiste architectural costs, takie jak globalne ładowanie edytora kodu na dashboardzie lub polling setek widżetów co sekundę.
