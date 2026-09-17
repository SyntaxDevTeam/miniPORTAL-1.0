# 13 — Versioning, Compatibility and Releases

## 1. SemVer

Publiczne API Core, Libraries, UI, capabilities, modules i themes powinny używać semantycznego wersjonowania tam, gdzie package jest niezależnie wersjonowany.

`MAJOR.MINOR.PATCH`:

- MAJOR — breaking public contract,
- MINOR — kompatybilna nowa funkcja,
- PATCH — kompatybilna poprawka.

## 2. Rozdzielenie wersji

Nie należy utożsamiać:

- wersji miniPORTAL distribution,
- Core API version,
- UI API version,
- capability version,
- wersji modułu,
- wersji theme.

Distribution 1.2 może nadal oferować `filesystem capability 1.x`, jeśli contract się nie zmienił breaking.

## 3. Manifest constraints

Packages używają jawnych constraints, np.:

```text
core: ^1.0
uiApi: ^1.1
filesystem: ^1.2
```

Parser constraints jest częścią preflight.

## 4. Public vs internal

Namespace/API musi jasno określać publiczne contracty. Kod `Internal` nie ma gwarancji kompatybilności i nie może być używany przez package zewnętrzny.

## 5. Deprecation policy

Przed usunięciem publicznego API preferujemy:

1. oznaczenie deprecated,
2. nowy replacement,
3. dokumentację migracji,
4. telemetry/dev warning, jeśli możliwe bez hałasu,
5. usunięcie w następnym MAJOR.

## 6. API compatibility check

CI powinno porównywać publiczne signatures z bazą poprzedniego release i klasyfikować zmianę.

Breaking change nie może przypadkowo wejść jako patch.

## 7. Theme compatibility

Theme deklaruje `uiApi` range. Nowy publiczny komponent w kompatybilnym minorze ma bazowy renderer, dlatego starszy theme może korzystać z fallbacku.

Breaking zmiana semantyki komponentu wymaga UI API major albo kompatybilnej warstwy adaptera.

## 8. Module compatibility

Module zależy od contracts/capabilities. Nie powinien deklarować zależności od pełnej wersji distribution, jeśli naprawdę potrzebuje tylko konkretnego API.

## 9. Migration compatibility

Każdy release z migracją opisuje:

- from schema/version,
- to schema/version,
- reversibility,
- prerequisites,
- expand/contract phase,
- backup requirement,
- expected lock/downtime behavior.

## 10. Release channels

Proponowane kanały:

- `dev` — ciągłe development builds,
- `alpha` — niekompletne API, architektura w aktywnym kształtowaniu,
- `beta` — feature-complete Core, API stabilizowane,
- `rc` — release candidate, głównie poprawki,
- `stable` — 1.0+.

## 11. Reproducibility

Release powinien mieć:

- pinned lockfile,
- build metadata,
- checksum artifacts,
- changelog,
- schema/migration plan,
- compatibility metadata.

## 12. Package artifact

Docelowy pakiet modułu/theme powinien być immutable. Po opublikowaniu wersji `1.2.3` nie podmieniamy jej treści; publikujemy `1.2.4`.

Ułatwia to rollback i audyt.

## 13. Activation history

Core przechowuje historię:

```text
package
from version
to version
actor
time
preflight result
migration ids
activation result
rollback reference
```

## 14. Core upgrade compatibility matrix

Przed aktywacją Core update system powinien sprawdzić:

- active modules constraints,
- active theme UI constraints,
- providers capability compatibility,
- pending migrations.

Niezgodność jest raportowana przed przełączeniem release.

## 15. Release Definition of Done

Release nie jest gotowy tylko dlatego, że build powstał. Musi mieć:

- zielone required checks,
- brak nierozwiązanych breaking compatibility errors,
- changelog,
- migration metadata,
- docs update,
- upgrade/rollback test odpowiedni do ryzyka.