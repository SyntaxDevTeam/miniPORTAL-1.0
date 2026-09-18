# AGENTS.md — miniPORTAL 1.0

Ten plik jest nadrzędną instrukcją dla agentów AI, automatycznych refaktorów i narzędzi generujących kod w tym repozytorium. Dokumentacja opisuje architekturę, ale niniejsze zasady określają sposób wprowadzania zmian.

## 1. Najważniejsza zasada

Nie naprawiaj lokalnego problemu przez obchodzenie kontraktów projektu.

Jeżeli moduł potrzebuje możliwości, której nie zapewnia publiczne API, nie wolno bezpośrednio sięgać do wewnętrznej implementacji Core. Należy:

1. sprawdzić istniejący publiczny contract/capability,
2. jeśli go brakuje — zaprojektować rozszerzenie publicznego kontraktu,
3. dodać implementację bazową i testy kontraktowe,
4. dopiero potem użyć nowej możliwości w module.

## 2. Granice architektury

### Module może zależeć od

- publicznych kontraktów Core,
- publicznych kontraktów bibliotek,
- UI API,
- capabilities zadeklarowanych w swoim manifeście,
- innego modułu wyłącznie wtedy, gdy zależność jest jawnie zadeklarowana i architektura ją dopuszcza.

### Module nie może

- importować `Core/Internal/*`,
- wykonywać własnego bootstrapu infrastruktury,
- wykonywać bezpośrednich operacji filesystem, jeśli istnieje Filesystem API,
- tworzyć własnego połączenia DB poza przewidzianą warstwą storage,
- emitować przypadkowego globalnego CSS,
- modyfikować theme,
- zakładać konkretnego layoutu,
- kodować bezpośrednio szczegółów transportu htmx/SSE/WebSocket, jeśli UI/Realtime API zapewnia abstrakcję,
- przechowywać sekretów w logach,
- wymuszać aktywacji innego modułu bez dependency contract.

### Theme może

- dostarczać design tokens,
- nadpisywać renderery komponentów,
- dostarczać layouty,
- dostarczać własne assety prezentacyjne.

### Theme nie może

- wykonywać zapytań SQL,
- wykonywać operacji filesystem domeny,
- zmieniać autoryzacji,
- rejestrować logiki biznesowej,
- wywoływać Node Agentów,
- zastępować publicznych kontraktów Core.

## 3. UI

Moduł powinien budować semantyczne `PageDefinition` i komponenty UI. Nie tworzy osobnego systemu HTML/CSS dla standardowych elementów.

Przed stworzeniem nowego komponentu należy sprawdzić, czy problem da się wyrazić przez istniejący komponent lub kompozycję komponentów.

Nowy komponent publiczny wymaga:

- kontraktu w UI API,
- bazowego renderera w Base Theme,
- stanów demonstracyjnych w UI Catalog,
- testów kontraktowych,
- testów dostępności w rozsądnym zakresie,
- visual regression fixtures,
- dokumentacji zachowania responsywnego.

Nie wolno wymagać jednoczesnego dopisania komponentu do wszystkich theme. Base Theme jest obowiązkowym fallbackiem.

## 4. htmx i JavaScript

htmx jest szczegółem implementacyjnym UI Core. Kod modułu nie powinien ręcznie składać `hx-get`, `hx-target`, `hx-swap` itd., jeśli tę samą operację można opisać poprzez UI API.

JavaScript należy dodawać wyłącznie wtedy, gdy natywny HTML/CSS, htmx lub istniejące API nie wystarczają. Ciężkie biblioteki mają być ładowane lazy.

## 5. Zmiana scope

Każdy PR powinien mieć określony scope. Jeżeli zadanie dotyczy modułu, agent nie powinien „przy okazji” refaktoryzować Core, theme ani niepowiązanych bibliotek.

Jeżeli zmiana przekracza pierwotny scope, należy to jawnie opisać w PR wraz z powodem oraz wpływem na kompatybilność.

## 6. Publiczne API i kompatybilność

Zmiana publicznego contractu wymaga:

- oceny semver,
- aktualizacji testów kontraktowych,
- impact analysis,
- sprawdzenia zależnych modułów/theme,
- notatki migracyjnej, jeśli zmiana nie jest kompatybilna wstecz.

Nie usuwaj publicznej funkcji tylko dlatego, że obecny kod jej nie używa. Publiczne API jest kontraktem również dla przyszłych/zewnętrznych dodatków.

## 7. Migracje

Migracja bazy nie może być wykonywana przy samym wykryciu pakietu. Najpierw musi istnieć plan migracji oraz możliwość preflight.

Preferuj expand/contract. Unikaj destrukcyjnych migracji w tym samym release, w którym kod zaczyna polegać na nowym schemacie.

## 8. Obsługa błędów

Błąd modułu ma pozostać błędem modułu. Nie wolno maskować błędu pustym `catch`, ale nie wolno też pozwalać, aby wyjątek modułu przeszedł przez granicę Module Dispatcher i uszkodził cały request Core.

Każdy raportowany błąd powinien posiadać correlation/error ID, a szczegół techniczny trafiać do logu zamiast do publicznego UI.

## 9. Testy obowiązkowe

Przed zakończeniem zmiany uruchom docelowe polecenie weryfikacyjne projektu:

```bash
composer verify
```

Do czasu implementacji tego polecenia należy traktować dokumentację `docs/10-TESTING-CI-QUALITY-GATES.md` jako definicję jego przyszłej zawartości.

Docelowo `composer verify` musi obejmować co najmniej:

- Composer validation,
- PHP syntax/lint,
- static analysis,
- architecture tests,
- unit tests,
- integration tests,
- module/library contract tests,
- theme/UI contract tests,
- migration checks,
- Playwright/browser tests tam, gdzie dotyczą zmiany.

Agent nie może oznaczać pracy jako ukończonej, gdy wymagane testy nie przeszły.

## 10. Dokumentacja

Zmiana architektury bez aktualizacji dokumentacji jest niekompletna.

Jeżeli kod zaczyna zachowywać się inaczej niż dokumentacja, należy w tym samym PR zaktualizować odpowiedni dokument lub dodać ADR.

## 11. Zakazy tymczasowe podczas budowy Core

Dopóki roadmapa nie osiągnie milestone'u zezwalającego na moduły domenowe, nie należy implementować Minecraft/PunisherX/VPS/StableManagerX tylko po to, aby „mieć przykład”. Do walidacji architektury używamy małych fixture/test modules.

## 12. Kryterium ukończenia pracy agenta

Zmiana jest ukończona wyłącznie wtedy, gdy:

- zachowuje granice warstw,
- nie zwiększa niejawnych zależności,
- ma testy odpowiednie do ryzyka,
- nie pogarsza fallbacków theme,
- nie powoduje globalnej awarii przy lokalnym błędzie,
- nie obchodzi preflight/activation lifecycle,
- dokumentacja i kontrakty odpowiadają kodowi,
- pełny wymagany zestaw CI jest zielony.

## 13. Commit batching i push policy

Agent nie powinien wykonywać zdalnego push po każdej drobnej zmianie ani po każdym pojedynczym małym commicie.

Preferowany model pracy:

1. wykonuj lokalne commity wtedy, gdy zamykają logiczną część pracy,
2. zgromadź spójny pakiet zmian — zwykle około 3–5 logicznych commitów albo jeden zamknięty podetap,
3. uruchom odpowiednie testy/weryfikację dla całego pakietu,
4. dopiero wtedy wykonaj jeden push na zdalny branch.

Liczba 3–5 jest wskazówką, a nie celem samym w sobie. Nie wolno tworzyć sztucznych, pustych ani przesadnie rozdrobnionych commitów tylko po to, aby osiągnąć konkretną liczbę.

Push wcześniej jest uzasadniony, gdy:

- potrzebne jest zdalne CI do dalszej diagnozy,
- następuje handoff pracy do innego człowieka/agenta,
- przed ryzykowną operacją potrzebny jest bezpieczny punkt zdalny,
- użytkownik jawnie prosi o natychmiastowy push,
- zmiana jest pilnym hotfixem/security fixem.

Celem tej polityki jest ograniczenie szumu w historii zdalnej i niepotrzebnych uruchomień CI, bez utraty czytelnych, logicznych commitów.
