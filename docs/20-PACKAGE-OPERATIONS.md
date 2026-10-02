# 20 — Planowane operacje managera pakietów

`PackageOperator` obsługuje `activate`, `disable`, `uninstall` i `rollback` jako dwie osobne
fazy. `packages:plan OP ID [VERSION]` odczytuje registry, sprawdza politykę
modułów wymaganych, aktywne zależności i zgodność docelowego zestawu, po czym
zwraca blokady oraz SHA-256 stanu. `packages:apply OP ID VERSION CHECKSUM`
ponownie oblicza plan; gdy stan się zmienił, odmawia operacji. Przykład:

```text
bin/miniportal packages:plan disable optional.pages
bin/miniportal packages:apply disable optional.pages 1.0.0 CHECKSUM_Z_PLANU
```

`disable` wyłącza trasę i widgety przez usunięcie wskaźnika aktywnej wersji,
bez kasowania danych. `uninstall` usuwa tylko metadane konkretnego wydania
po jego wyłączeniu; dane i pliki pozostają do osobnej, świadomej operacji.
`rollback` przełącza wskaźnik na zachowane, wcześniej aktywowane wydanie po
ponownej kontroli zgodności zależności. Manager blokuje wyłączenie aktywnego
modułu, od którego zależy inny aktywny moduł. Systemowy `system.themes` nie
może być wyłączony ani odinstalowany; ten warunek działa w Core, nie tylko w
panelu. Wskaźnik aktywnej wersji w bazie zmienia się transakcyjnie.

`ReviewedPackageInstaller` przyjmuje wyłącznie katalog w repozytorium
`modules/`. `packages:install:plan DIRECTORY` czyta manifest, sprawdza składnię
PHP, klasę wejściową, zależności całego aktywnego zestawu, historię i preflight
migracji oraz zwraca sumę plików i stanu registry. `packages:install:apply
DIRECTORY CHECKSUM` powtarza kontrole, kopiuje pliki do niemutowalnego
`var/packages/ID/releases/VERSION`, uruchamia preflight i migracje, po czym
pozostawia wydanie w stanie `ready`. **Instalacja lub aktualizacja nie aktywuje
wydania.** Osobny plan i wykonanie `activate` przełączają aktywną wersję.
Dotyczy to także nowego wydania wymaganego modułu `system.themes`; manager
sprawdza zgodność całego aktywnego zestawu przed przełączeniem.
Migracje wymagające backupu albo destrukcyjne są blokowane; potrzebują osobnej,
zatwierdzonej procedury. Usunięcie metadanych nie usuwa plików ani danych.

Opcjonalny moduł może implementować publiczny `ModuleMigrationProvider` i
zwrócić pełną uporządkowaną listę definicji, także tych już wykonanych. Nowa
wersja musi zachować ich checksumy; brak starej definicji blokuje aktualizację.
Runtime ładuje wyłącznie aktywne wydanie. Każdy wyjątek ładowania zostaje
zalogowany z error ID i nie blokuje pozostałych modułów ani Core. Moduły są
ładowane w kolejności zależności; moduł z uszkodzoną zależnością nie jest
montowany. Klasa
wejściowa modułu musi implementować `Module`, być w przestrzeni nazw
`SyntaxDevTeam\\MiniPortal\\Module\\DIRECTORY\\` i mieć konstruktor bez
wymaganych argumentów albo implementować publiczny `ModuleFactory`. Fabryka
otrzymuje `ModuleServices` z publicznym `UiFacade` (renderowanie i odczyt
motywów), kontraktem storage i ograniczonym `ModuleIdentity` (odczyt sesji oraz
CSRF). Nie otrzymuje mutowalnego rejestru motywów ani menedżera logowania;
`system.themes` korzysta z tej samej drogi.

Ten instalator służy do **sprawdzonego kodu dostarczonego z repozytorium**.
Preflight wykonywany jest przez CLI i może załadować klasę modułu, więc nie jest
sandboxem dla nieznanych paczek. Zdalny upload/marketplace wymaga oddzielnego
modelu zaufania i izolacji procesów.

Kontrakt `PackageRegistry::remove()` oraz metody managera są addytywne (minor).
Adaptery registry muszą dodać `remove()`. Istniejące manifesty nie zmieniają
formatu. Nowy kontrakt migracji jest addytywny (minor). Testy obejmują provider pamięciowy i PDO, blokady modułu wymaganego,
zależnych pakietów, usunięcie wydania, rollback oraz odrzucenie nieaktualnego
planu, loader i checksum planu instalacji. Przed włączeniem pakietu domenowego
trzeba dodać test jego migracji oraz tras i UI.
