# 11 — AI Agent Governance

## 1. Problem

Agent AI potrafi szybko rozwijać kod, ale równie szybko może:

- ominąć istniejącą abstrakcję,
- stworzyć równoległy wzorzec,
- dopisać UI tylko dla jednego theme,
- rozszerzyć scope zadania,
- naprawić test przez osłabienie testu,
- zmienić publiczne API bez oceny wpływu.

Rozwiązaniem nie jest wyłącznie dłuższy prompt. Architektura i CI muszą ograniczać możliwy blast radius.

## 2. Zasada: guardrails > pamięć

Dokumentacja mówi agentowi jak pracować. Narzędzia sprawdzają, czy faktycznie tak pracował.

Każda ważna reguła powinna mieć co najmniej jedno z:

- type/interface boundary,
- namespace dependency rule,
- manifest schema,
- contract test,
- runtime validation,
- CI check.

## 3. `AGENTS.md`

Korzeń repo zawiera nadrzędne instrukcje. Podkatalog może mieć bardziej szczegółowe AGENTS, ale nie może osłabiać zasad nadrzędnych bez jawnej decyzji architektonicznej.

Agent przed zmianą powinien przeczytać:

- root `AGENTS.md`,
- dokument architektury dotyczący zmienianej warstwy,
- lokalne contract tests.

## 4. Scope declaration

Każdy większy PR powinien deklarować typ zmiany:

```text
module
library/provider
ui-component
theme
core
cross-cutting
docs
```

Dla zmiany lokalnej oczekiwany zakres plików jest ograniczony.

Jeżeli agent edytuje pliki poza zakresem, PR powinien uzasadnić dlaczego.

## 5. Scope guard

Docelowe CI może mapować deklarowany scope do dozwolonych obszarów.

Przykład:

```text
Declared: module/minecraft-console
Unexpected changes:
- src/Core/Security/Authorizer.php
- themes/base/layouts/application.php

Result: review required / fail depending on policy
```

Nie chodzi o całkowity zakaz zmian cross-cutting, tylko o ich widoczność.

## 6. Agent nie może tworzyć standardowego UI lokalnie

Jeśli agent potrzebuje nowego rodzaju kontrolki:

1. sprawdza UI API,
2. próbuje kompozycji istniejących komponentów,
3. jeśli to generyczny brak — proponuje nowy publiczny component,
4. dodaje Base Theme renderer + Catalog + tests,
5. jeśli to komponent domenowy — moduł dostarcza fallback renderer.

Nigdy: „dopisałem HTML do jednego theme i TODO dla reszty”.

## 7. Agent nie może tworzyć równoległej infrastruktury

Przykłady zabronione:

- własny filesystem helper w module,
- własny cache wrapper mimo CacheContract,
- osobny permission system,
- ręczny SSE server per module,
- osobny mechanism update w konkretnym dodatku.

Jeśli publiczne API nie wystarcza, rozszerzamy contract centralnie.

## 8. Public API change protocol

Agent zmieniający publiczny contract musi w PR odpowiedzieć:

- czy zmiana jest kompatybilna wstecz,
- które implementation/providers są dotknięte,
- które modules/themes są dotknięte,
- czy wymagany jest semver bump,
- czy istnieje migration/deprecation path.

CI powinno potwierdzić impact tam, gdzie możliwe.

## 9. Zakaz „naprawiania CI” przez obniżanie jakości

Agent nie może bez uzasadnienia:

- wyłączać testu,
- dodawać blanket ignore static analysis,
- obniżać level analysis,
- usuwać architecture rule,
- zwiększać visual diff tolerance,
- usuwać assertion tylko dlatego, że implementacja jej nie spełnia.

Zmiana guardraila jest zmianą architektury i wymaga jawnej decyzji.

## 10. PR template jako kontrakt pracy

PR powinien zawierać:

- cel,
- scope,
- warstwy dotknięte zmianą,
- public API impact,
- migration impact,
- UI/theme impact,
- security impact,
- test evidence,
- docs/ADR update.

## 11. Machine-readable architecture map

W późniejszym milestone warto utrzymywać plik konfiguracyjny opisujący warstwy i dozwolone zależności. Ten sam plik może zasilać:

- architecture tests,
- change impact tool,
- agent context generator,
- docs graph.

W ten sposób agent nie musi rekonstruować architektury z setek plików.

## 12. Task context pack

Dla większego zadania narzędzie developerskie może generować minimalny context pack:

```text
relevant contracts
relevant docs
relevant tests
allowed dependency boundaries
affected packages
```

To ogranicza halucynowanie nieistniejących API i przypadkowe sięganie po stary wzorzec.

## 13. Architecture lint messages mają być instruktywne

Zamiast:

```text
Dependency violation
```

lepiej:

```text
ARCH-004: Module `minecraft-files` imports Core/Internal/FilesystemManager.
Modules may depend on FilesystemContract only.
See docs/04-SERVICE-LIBRARIES-FILESYSTEM.md.
```

Dobre komunikaty prowadzą agenta do poprawnego wzorca.

## 14. Fixture-driven development

Przy zmianach Core agent powinien najpierw/razem dodać fixture pokazujące scenariusz, np. moduł z błędnym manifestem. To utrudnia „naprawienie” problemu wyłącznie dla obecnego przypadku.

## 15. Protected main

Docelowy workflow:

```text
agent/user branch
  ↓
PR
  ↓
required CI
  ↓
review/merge
  ↓
main
```

Bez bezpośredniego push do `main` dla zmian kodowych, gdy projekt osiągnie etap aktywnej implementacji.

## 16. AI review nie zastępuje testu

Drugi agent może wykonać review, ale nie zastępuje deterministycznego CI. Agent jest dobry w semantyce i wykrywaniu ryzyk; test jest lepszy w pilnowaniu stałego contractu.

## 17. Najważniejszy rezultat

Celem nie jest ograniczenie agentów AI. Celem jest umożliwienie im szybkiej pracy **bez możliwości cichego rozjechania architektury**. Agent powinien móc eksperymentować na branchu, ale merge następuje dopiero po przejściu systemowych guardrails.