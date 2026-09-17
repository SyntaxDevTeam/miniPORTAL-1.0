# 08 — Resilience, Package Update and Rollback

## 1. Nadrzędna zasada

Instalacja plików nie oznacza aktywacji kodu.

Każdy pakiet (module/theme/provider) przechodzi kontrolowany lifecycle:

```text
UPLOAD/DOWNLOAD
  ↓
QUARANTINE
  ↓
INTEGRITY
  ↓
MANIFEST VALIDATION
  ↓
DEPENDENCY RESOLUTION
  ↓
ISOLATED PREFLIGHT
  ↓
MIGRATION PLAN
  ↓
SMOKE TEST
  ↓
READY
  ↓
ATOMIC ACTIVATION
  ↓
POST-ACTIVATION HEALTH
```

## 2. Release directories

Proponowany model logiczny:

```text
packages/<id>/
├ releases/
│ ├ 1.7.2/
│ └ 1.8.0/
└ active -> 1.7.2
```

Na Linux `active` może być symlinkiem, ale publiczny model nie powinien zależeć wyłącznie od symlinków. Alternatywnie stan aktywnej wersji jest transakcyjnie zapisany w release registry, a loader korzysta z immutable release path.

## 3. Quarantine

Nowy pakiet nie jest automatycznie dostępny dla autoloadera aplikacji produkcyjnej.

W quarantine można wykonać:

- checksum/integrity,
- archive traversal validation,
- manifest schema,
- duplicate ID checks,
- forbidden path checks,
- dependency metadata analysis.

## 4. PHP syntax i autoload preflight

Sam `php -l` jest potrzebny, ale niewystarczający.

Preflight powinien uruchomić osobny proces PHP CLI z:

- kontrolowanym `memory_limit`,
- timeoutem,
- temp working directory,
- staging autoload,
- bez produkcyjnego request context,
- bez niepotrzebnego dostępu do sekretów,
- capture stdout/stderr/exit code.

Proces testuje co najmniej:

- syntax,
- class loading,
- entrypoint interface,
- registration phase,
- declared contract versions,
- package self-test hook, jeśli istnieje.

Awaria procesu preflight nie wpływa na FPM/Core.

## 5. Dependency preflight

Przed aktywacją resolver potwierdza:

- Core API compatibility,
- UI API compatibility,
- required capabilities,
- required modules,
- conflicts,
- cycle absence.

Błąd daje raport, nie globalny wyjątek.

## 6. Migration plan

Pakiet deklaruje migracje oddzielnie od bootu.

Przed aktywacją:

```text
migrations:plan
  ↓
show pending changes
  ↓
validate prerequisites
  ↓
optional disposable/sandbox test
```

Migracji nie uruchamia się podczas samego discovery.

## 7. Expand/contract

Preferowany sposób zmian schematu:

Release A:
- dodaje nową strukturę,
- stary kod nadal działa.

Release B:
- przełącza read/write na nową strukturę.

Release C:
- po okresie kompatybilności usuwa starą strukturę.

Pozwala to zachować rollback code path.

## 8. Destrukcyjne migracje

Jeżeli migracja jest nieodwracalna:

- musi być jawnie oznaczona,
- UI pokazuje brak automatycznego rollbacku danych,
- może wymagać backupu/snapshot precondition,
- activation plan zapisuje recovery instructions.

Nie należy udawać, że rollback kodu automatycznie oznacza rollback danych.

## 9. Atomic activation

Po wszystkich testach zmieniamy active release w jednej kontrolowanej operacji.

Wymagania:

- stary release pozostaje dostępny przez retention window,
- cache/opcache invalidation odbywa się w kontrolowany sposób,
- jobs/routes registry przechodzą na nową wersję spójnie,
- aktywacja zapisuje audit record.

## 10. Post-activation smoke test

Po aktywacji system sprawdza:

- module boot,
- route registration,
- health hook,
- minimalny request/use case,
- krytyczne dependencies.

Jeśli bezpieczny auto-rollback jest możliwy, system może wrócić do poprzedniego release.

## 11. Runtime isolation

Warstwa 1: `try/catch Throwable` i Module Dispatcher dla normalnych błędów PHP.

Warstwa 2: circuit breaker/health state dla powtarzalnych awarii.

Warstwa 3: osobny proces/worker dla preflight i potencjalnie zadań wysokiego ryzyka.

Warstwa przyszła: możliwość isolated module worker/FPM pool dla dodatków, których nie chcemy uruchamiać in-process.

## 12. Ograniczenie izolacji in-process

Nie należy obiecywać pełnej izolacji procesu w zwykłym PHP include. OOM procesu, crash native extension czy inne process-level faults mogą zabić worker.

Dlatego:

- Core minimalizuje zaufany in-process surface,
- package preflight jest poza request workerem,
- architektura nie zamyka drogi do process isolation.

## 13. Module health states

Proponowane statusy:

```text
HEALTHY
DEGRADED
UNAVAILABLE
DISABLED
FAILED_PREFLIGHT
INCOMPATIBLE
MIGRATION_BLOCKED
```

Stan jest widoczny w panelu diagnostycznym.

## 14. Circuit breaker

Przykładowa polityka:

```text
5 unexpected failures / 60 s
→ DEGRADED
→ stop selected background jobs
→ cooldown
→ health probe
→ recover or DISABLED
```

Progi są konfigurowalne i muszą odróżniać user validation errors od realnych awarii.

## 15. Emergency mode

Core powinien mieć tryb awaryjny niezależny od aktywnych modułów/theme:

- Base Theme/emergency layout,
- lista package states,
- disable package,
- rollback package,
- log/error ID lookup,
- cache clear,
- migration status.

Jest to ważne, aby naprawa uszkodzonego rozszerzenia nie wymagała ręcznego grzebania w bazie/plikach.

## 16. Aktualizacja Core

Core jest szczególnym przypadkiem. Nie da się zagwarantować tego samego poziomu izolacji jak dla niezależnego modułu.

Dlatego aktualizacja Core wymaga surowszego procesu:

- full verify,
- compatibility matrix packages/themes,
- maintenance/recovery plan,
- backup wymagany przy migracjach,
- staged release directory,
- możliwość rollback binary/code, jeśli schema na to pozwala.

## 17. Panel aktualizacji

UI powinno pokazywać preflight jako raport:

```text
Minecraft Console 1.8.0
✓ integrity
✓ manifest schema
✓ core API ^1.0
✓ filesystem ^1.3
✓ PHP syntax
✓ isolated autoload
✓ registration smoke test
✓ migration plan
✓ theme/UI contracts

Status: READY TO ACTIVATE
```

Administrator widzi konkretną przyczynę blokady zamiast HTTP 500 po fakcie.