# 19 — API usługowe v1

Moduły rejestrują endpointy przez publiczny `ModuleRegistration::api`. Trasa
ma postać `/api/v1/modules/{moduleId}/{path}` i jest publikowana dopiero po
udanym `register()` oraz `boot()`. Każde żądanie ponownie sprawdza wskaźnik
aktywnej wersji; wyłączony moduł zwraca 404. Handler musi zwrócić JSON przez
`Response::json()`. Wyjątek albo niezgodny format odpowiedzi daje bezpieczne
503 z identyfikatorem błędu, a szczegół techniczny pozostaje w logu.

Gateway przyjmuje tylko `Authorization: Bearer mp1_<id>_<secret>`. W bazie
`core.api` jest przechowywany SHA-256 losowego 256-bitowego sekretu, zakresy
uprawnień, termin ważności i stan odwołania. Sekret jest widoczny tylko raz
przy wydaniu. Token usługi nie tworzy sesji przeglądarkowej i nie przejmuje
CSRF; `RequestContext` wskazuje principal `service:<tokenId>` i zakresy.
Centralny gateway wymaga zadeklarowanego scope, ogranicza żądania domyślnie do
120 na minutę na token oraz zwraca 401, 403 lub 429 w JSON. Handler nadal
odpowiada za autoryzację dostępu do konkretnych zasobów. HTTPS jest wymagane
na granicy serwera przy użyciu tokenu poza lokalnym testem.

Po `bin/miniportal migrations:plan` i `migrations:apply` administrator może
wydać token lokalnym poleceniem `bin/miniportal api:token:issue themes.read 30`
(ostatni argument: dni, maksymalnie 365). Polecenie drukuje identyfikator i
sekret jeden raz; należy przekazać je bezpiecznym kanałem i nie zapisywać w
repozytorium ani logach. `bin/miniportal api:token:revoke TOKEN_ID` odwołuje
token. Moduł systemowy szablonów udostępnia testowy endpoint odczytu listy
szablonów pod `/api/v1/modules/system.themes/themes` ze scope `themes.read`.

Kontrakt rejestracji jest addytywny (minor). Istniejące moduły bez endpointów
API nadal się montują. Moduły muszą deklarować tylko GET/POST, unikalną nazwę,
ścieżkę względną i pojedynczy wymagany scope. UI routes i API są buforowane
razem; błąd rejestracji nie publikuje części tras. Wymagany jest plan przed
migracją, a samo wykrycie manifestu nie tworzy tabel ani tokenów.

Aktualny licznik rate limit tworzy wiersz na token i minutę. Przed dużym ruchem
trzeba dodać okresowe czyszczenie starych liczników w workerze/CLI. Nie należy
bez tego deklarować API jako gotowego do wysokiego obciążenia.
