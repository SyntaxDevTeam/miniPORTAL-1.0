# ADR-0012: Core, moduły systemowe, dodatki, widgety i API

Status: Accepted
Date: 2026-10-02

## Kontekst

miniPORTAL 1.0 ma być sprawnym Core z modułami. Dotychczasowa dokumentacja
opisywała warstwy i moduły domenowe, ale nie rozróżniała jednoznacznie
niewyłączalnych modułów systemowych, preinstalowanych dodatków oraz widgetów.
Decyzja utrwala wymaganie właściciela projektu i obowiązuje przy dalszej implementacji.

## Decyzja

| Część | Odpowiedzialność | Zasady lifecycle |
| --- | --- | --- |
| Core | Bootstrap, publiczne kontrakty, routing, centralna autoryzacja, izolacja awarii, manager modułów oraz mechanizm instalacji, aktualizacji, migracji, wyłączania, usuwania i rollbacku | Działa bez dodatków domenowych; zapewnia diagnostykę i odzyskiwanie instalacji |
| Moduły systemowe | Wydzielone pakiety ściśle związane z Core, niezbędne do działania platformy; w szczególności obsługa i zarządzanie szablonami | Wymagane, niewyłączalne i nieusuwalne w zwykłym lifecycle; aktualizowane jako zgodny zestaw z Core |
| Dodatki | Moduły opcjonalne: proste strony, artykuły, zarządzanie treścią/witryną, a później m.in. zarządzanie serwerami Minecraft | Mogą być preinstalowane przez profil instalacji; można je aktywować, wyłączać, aktualizować i odinstalowywać po analizie zależności |
| Widgety | Niezależnie konfigurowane instancje treści/funkcji dostarczanych przez moduły, rozmieszczane na stronach | Podlegają lifecycle modułu właściciela i centralnej autoryzacji; awaria jednej instancji pozostaje lokalna |
| API i endpointy usług | Wersjonowane publiczne interfejsy dla integracji, klientów usługowych i modułów | Rejestracja przez publiczny kontrakt; endpointy dodatku działają wyłącznie dla aktywnej, dopuszczonej wersji |

To podział odpowiedzialności, a nie pięć nowych wartości pola `type` manifestu.
Moduł systemowy i dodatek należą do rodziny modułów; preinstalacja jest polityką
dystrybucji. Widget jest wkładem modułu do UI, a endpoint wkładem do routingu.
Biblioteki, providery i pakiety theme zachowują istniejące role techniczne.

## Core i moduły systemowe

Manager oraz egzekwowanie lifecycle należą do Core. Interfejsy administracyjne
obsługi platformy powinny być wydzielane do odpowiednich modułów systemowych,
a funkcje zarządzania treścią do dodatków. Nie należy rozbudowywać front
controllera o kolejne funkcje domenowe.

Status modułu systemowego wynika z zaufanej definicji dystrybucji Core.
Zewnętrzny pakiet nie może sam nadać sobie niewyłączalności przez manifest.
Manager odrzuca disable/uninstall wymaganego modułu zarówno w UI, CLI, jak i API;
samo ukrycie przycisku nie wystarcza. Preflight sprawdza kompletność i zgodność
zestawu Core + moduły systemowe przed aktywacją.

Niewyłączalność nie oznacza pominięcia izolacji awarii. Błąd modułu systemowego
powoduje bezpieczny stan degraded i uruchomienie diagnostyki/odzyskiwania Core,
a nie zwykłe automatyczne odinstalowanie czy wyłączenie wymaganej zależności.
Core zachowuje minimalną ścieżkę awaryjną niezależną od opcjonalnych dodatków.

Moduł obsługi szablonów jest wymagany; konkretny szablon jest wymiennym pakietem
prezentacyjnym. Base Theme pozostaje gwarantowanym fallbackiem. Renderer theme
nadal nie może wykonywać logiki biznesowej ani operacji domenowego storage.

## Dodatki i preinstalacja

Profil instalacji może dostarczać np. Strony i Artykuły od razu, lecz ich
obecność nie czyni ich częścią Kernel ani niewyłączalnymi modułami systemowymi.
Instalator korzysta z tego samego planu, preflightu, migracji i aktywacji co
manager. Preinstalacja nie wykonuje kodu podczas discovery.

Disable zatrzymuje routes, endpointy API, jobs i wkłady UI/widgety pakietu,
zachowując dane oraz konfigurację instancji. Uninstall usuwa pakiet według
planu zależności; purge danych jest odrębną, jawną operacją. System odrzuca
operację naruszającą wymagane zależności lub przedstawia jawny plan zmian
zależnych dodatków. Nie wykonuje niejawnego kaskadowego usuwania.

## Widgety w dowolnych miejscach strony

UI API musi umożliwiać deklarowanie nazwanych punktów osadzania (slotów) w
layoutach oraz wewnątrz drzewa treści strony. Nie ograniczamy widgetów do
sztywnej listy typu sidebar/footer. Autor strony/layoutu może dodać punkt w
dowolnym miejscu kompozycji, a administrator przypisuje do niego instancje,
kolejność, konfigurację i reguły widoczności przez publiczne API.

Widget zwraca semantyczne komponenty UI i korzysta z fallbacku Base Theme.
Nie modyfikuje plików theme, nie wstrzykuje dowolnego HTML/JS ani nie szuka
selektorów DOM. Widoczność nie zastępuje autoryzacji odczytu danych i akcji.
Każda instancja ma stabilne ID oraz właściciela; wiele instancji jednego widgetu
może mieć różną konfigurację. Cache musi uwzględniać zakres użytkownika i permissions.

Po zmianie theme brakujący slot zachowuje przypisania jako nieumieszczone do
ponownego przypisania; nie usuwa danych i nie publikuje automatycznie prywatnej
treści w innym miejscu. Awaria widgetu daje lokalny error/degraded state z ID.

## API i endpointy dla usług

Core zapewnia kontrakt rejestracji, namespacing, wersjonowanie, kontekst
uwierzytelnienia, egzekwowanie permissions/scopes, limity żądań i bezpieczne
błędy z correlation ID. Moduły deklarują własne endpointy i wymagane uprawnienia,
a logikę usługową realizują przez publiczne kontrakty bibliotek.

Obsługiwane mają być zarówno API platformy, jak i endpointy dodatków oraz
integracje service-to-service. Konkretna metoda uwierzytelniania usług wymaga
kontraktu i testów przed wdrożeniem; sesja przeglądarki nie jest automatycznie
modelem autoryzacji usług. Endpoint nie omija Module Dispatcher, lifecycle ani
centralnej kontroli dostępu. Transport HTTP/SSE/WebSocket pozostaje adapterem.

## Konsekwencje, kompatybilność i stan realizacji

Jest to przyjęty model docelowy, nie deklaracja ukończenia implementacji.
Obecny Kernel, UI, bazodanowy registry z migracją i chroniony mount zaufanych
modułów stanowią fundament; wydzielenie
modułów systemowych, profile preinstalacji, kontrakt widgetów i kompletne API
usług wymagają kolejnych prac. Obecna schema manifestu i publiczne interfejsy
nie zmieniają się w tym ADR. Rozszerzenia wymagają oceny semver, impact analysis,
testów kontraktowych i planu migracji istniejących pakietów.

Nie przenosimy obecnego kodu mechanicznie do `modules/`, nie dopuszczamy importu
Core internals przez moduły systemowe i nie implementujemy Minecraft przed
bramkami gotowości platformy. Kolejność oraz kryteria znajdują się w roadmapie.

## Weryfikacja i ponowne rozpatrzenie

Testy mają dowodzić: działania bez dodatków, odmowy disable/uninstall modułu
systemowego, bezpiecznej aktualizacji zestawu, opcjonalności preinstalowanych
dodatków, osadzania wielu widgetów w dwóch theme, izolacji ich awarii oraz
niedostępności endpointów nieaktywnego modułu i egzekwowania scopes API.
Zmiana tego podziału wymaga nowego ADR i jawnej decyzji właściciela projektu.
