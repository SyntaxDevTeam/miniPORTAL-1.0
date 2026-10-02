# 20 — Planowane operacje managera pakietów

`PackageOperator` obsługuje `disable`, `uninstall` i `rollback` jako dwie osobne
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

Kontrakt `PackageRegistry::remove()` oraz metody managera są addytywne (minor).
Adaptery registry muszą dodać `remove()`. Istniejące manifesty nie zmieniają
formatu. Testy obejmują provider pamięciowy i PDO, blokady modułu wymaganego,
zależnych pakietów, usunięcie wydania, rollback oraz odrzucenie nieaktualnego
planu. Dalszy etap obejmuje planowaną instalację i aktualizację z preflightem
kodu oraz migracji; same operacje na metadanych nie są instalatorem.
