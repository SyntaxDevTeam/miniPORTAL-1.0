# 17 — Legacy miniPORTAL Migration Strategy

## 1. Założenie

miniPORTAL 1.0 nie powinien powstawać jako chaotyczny rewrite wszystkich funkcji obecnej wersji. Najpierw budujemy platformę, później świadomie migrujemy możliwości.

## 2. Inwentaryzacja przed migracją

Każdy element legacy powinien otrzymać klasyfikację:

```text
CORE
LIBRARY
PROVIDER
MODULE
THEME/UI
DEPRECATED/REMOVE
UNKNOWN/REVIEW
```

Przykład:

- wspólne operacje na plikach → Filesystem Library,
- dostęp do konkretnego hosta → Provider,
- „Konsola Minecraft” → Module,
- wspólny wygląd tabel → UI/Base Theme,
- stary helper używany tylko historycznie → candidate remove.

## 3. Nie kopiujemy starego podziału katalogów

Migracja funkcji nie oznacza kopiowania klasy do nowej ścieżki. Najpierw identyfikujemy odpowiedzialność i contract.

## 4. Strangler approach

Jeśli potrzebne jest okresowe współistnienie starej i nowej wersji, funkcje można przenosić stopniowo. Nie należy jednak tworzyć permanentnej warstwy kompatybilności, która omija nowe boundaries.

## 5. Dane

Dla każdej legacy tabeli:

- owner,
- realny use case,
- obecny schema,
- problemy integralności,
- mapowanie do 1.0,
- migration strategy,
- rollback/recovery.

Nie należy migrować nieużywanych tabel „na wszelki wypadek”.

## 6. Users/auth/permissions

To obszar o podwyższonym ryzyku. Przed migracją potrzebne są testy mappingu:

- konta,
- role,
- permissions,
- sessions (czy migrujemy czy wymuszamy ponowne logowanie),
- audit/history.

Preferowana jest bezpieczna utrata sesji (ponowny login) zamiast ryzykownego przenoszenia niekompatybilnego session state.

## 7. Themes

Legacy theme nie powinien być automatycznie portowany jako copy-paste. Najpierw ustalamy:

- design tokens,
- layout roles,
- które component overrides faktycznie są unikalne.

Nowy Base Theme ma zastąpić duplikowane implementacje standardowych elementów.

## 8. Modules

Migracja modułu zaczyna się od usunięcia z niego infrastruktury, która w 1.0 należy do Libraries.

Przykład:

```text
Legacy Minecraft Files
  filesystem operations
  validation
  permissions
  HTML
  JS
  server lookup

1.0 Minecraft Files
  server lookup/domain
  FileScope definition
  permissions declaration
  FileBrowser composition
```

## 9. Compatibility adapters

Adapter legacy jest dopuszczalny jako rozwiązanie czasowe, jeśli:

- ma jasno oznaczony owner,
- nie staje się publicznym 1.0 API,
- ma plan usunięcia,
- jest testowany,
- nie osłabia security boundary.

## 10. Migration order

Rekomendowana kolejność:

1. Core users/auth minimum,
2. UI/basic settings,
3. generyczne libraries,
4. prosty reference module,
5. modules niskiego ryzyka,
6. filesystem/remote-heavy modules,
7. command/console/process control,
8. najbardziej złożone integracje.

## 11. Cutover

Przed finalnym cutover należy przygotować:

- backup,
- dry-run migracji,
- freeze window jeśli potrzebny,
- health checklist,
- recovery plan,
- możliwość powrotu do legacy przy braku destrukcyjnej migracji.

## 12. Kiedy legacy można wyłączyć

Dopiero gdy:

- wymagane dane są zmigrowane/zweryfikowane,
- podstawowe use cases mają parity lub świadomie zaakceptowaną zmianę,
- monitoring 1.0 nie pokazuje krytycznych regresji,
- administrator ma recovery path.
## 13. Przeprowadzony import tożsamości (2026-10-02)

Źródło: stara instalacja na VPS `57.128.212.27`, wyłącznie odczyt SQL przez SSH.
Docelowo dane są mapowane do nowego schematu `core.security`; struktura 107
starych tabel nie jest kopiowana. Import objął 12 kont, 16 tożsamości OAuth,
10 ról, 68 uprawnień, 13 przypisań ról i 227 przypisań uprawnień. Wskazanie
Ownera zostało odtworzone. Nie przenosimy sesji, haseł ani tokenów. Stary VPS
nie został zmieniony. Po imporcie liczności tabel docelowych odpowiadają
licznościom planu, a plan migracji schematu pokazuje zero oczekujących zmian.

Przed importem powstał prywatny zrzut SQL docelowej bazy:
`/home/debian/miniportal-backups/pre-legacy-identity-import-20261002T052455Z.sql`
(tryb 0600; zrzut obejmuje także stan registry po migracji `core.packages`,
ale przed aktywacją `system.themes` i przed importem tożsamości). Odtwarzanie
całej bazy z tego pliku usuwałoby późniejsze dane; wykonać je tylko po osobnym
backupie aktualnego stanu.

Powtarzalna procedura dla **pustej docelowej bazy tożsamości**:

1. `bin/miniportal migrations:plan` i, jeśli plan jest wykonywalny,
   `bin/miniportal migrations:apply`.
2. Wykonać prywatny backup docelowej bazy i sprawdzić, czy zrzut ma komplet
   tabel. Nie umieszczać zrzutu ani JSON z kontami w repozytorium.
3. Przesłać `bin/legacy-export-identities.php` na stdin `php` na starym VPS
   (SSH, tylko odczyt), a wynik bez zapisu na dysk przekazać do
   `bin/miniportal legacy:plan`. Zapisać wyłącznie liczności i SHA-256.
4. Powtórzyć identyczny strumień do `bin/miniportal legacy:apply CHECKSUM`.
   Import jest transakcyjny i odmawia nadpisania istniejących kont oraz
   zmienionego snapshotu. Jeśli źródło zmieniło się po planie, wykonać plan
   ponownie.
5. Sprawdzić liczności tabel `core.security`, wskazanie Ownera i logowanie
   OAuth z nową sesją. Nie wyłączać starego VPS przed sprawdzeniem funkcji,
   których danych jeszcze nie przeniesiono.

Pozostały do decyzji/implementacji import treści (`core_pages`: 10,
`homepage_sections`: 5, `homepage_section_items`: 18, `widgets`: 1,
`media_assets`: 20, `projects`: 16). Ich przeniesienie wymaga odpowiednich
modułów docelowych, mapowania pól, walidacji i osobnego preflightu. Obecny
import obejmuje tylko tożsamości i uprawnienia.
