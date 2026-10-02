# 18 — Widgety i sloty UI

`WidgetSlot` jest publicznym komponentem UI. Strona lub layout może umieścić go
w dowolnym punkcie swojego drzewa komponentów, także wewnątrz `Card` lub `Stack`.
`WidgetComposer::slot(pageId, slotId, requestContext)` pobiera instancje,
uwzględnia kolejność, aktywność modułu i wymagane uprawnienie, a następnie
zwraca semantyczny komponent. Base Theme renderuje slot, a Plasma dziedziczy
renderer; zmiana motywu nie zmienia danych ani przypisań.

Moduł rejestruje typ przez `ModuleRegistration::widgets`. Provider implementuje
`WidgetProvider` i zwraca listę komponentów UI. Kod providera uruchamia się
wyłącznie dla aktywnego modułu. `WidgetInstance` ma stabilne ID, ownera,
`pageId`, nazwę slotu, pozycję, płaską konfigurację oraz opcjonalne wymagane
uprawnienie. Przypisania zapisuje `WidgetPlacementRepository`, a bazodanowa
implementacja należy do `core.widgets`. Brak slotu w nowym theme nie usuwa
przypisań. Wyłączenie modułu nie usuwa jego konfiguracji, ale przestaje
renderować jego widgety. Wyjątek lub wadliwe drzewo jednego providera daje
lokalny `ErrorState` z ID; szczegóły trafiają tylko do logu.

Moduł nie powinien traktować ukrycia widgetu jako autoryzacji dostępu do danych.
Provider musi sam egzekwować politykę odczytu i akcji. To jest kontrakt
prezentacji, a nie endpoint API. Konfiguracja jest ograniczona do wartości
skalarnych i nie może zawierać sekretów. Wywołanie `slot()` pozostaje
jawne po stronie kompozycji strony; migracja przypisań jest planowana osobno,
a sam odczyt pakietu nie wykonuje migracji.

Przed aktywacją należy uruchomić `bin/miniportal migrations:plan`, potem
`migrations:apply`. Testy obejmują trwałość przypisań, dwa theme, uprawnienia,
wyłączenie modułu i izolację awarii. Publiczne rozszerzenia `ModuleRegistration`
i UI API są addytywne (minor); istniejące moduły nadal się montują.
