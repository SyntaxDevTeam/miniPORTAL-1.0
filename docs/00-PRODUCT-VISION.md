# 00 — Product Vision

## 1. Cel miniPORTAL 1.0

miniPORTAL 1.0 ma być lekką, rozszerzalną i odporną platformą administracyjną/CMS, która zachowuje prostotę klasycznego stosu PHP + HTML/CSS + SQL, ale wykorzystuje współczesne możliwości przeglądarki i warstwy hypermedia do tworzenia szybkiego, dynamicznego interfejsu.

Celem nie jest maksymalne wykorzystanie technologii frontendowych. Celem jest uzyskanie możliwie dobrego UX przy minimalnym koszcie runtime, małej liczbie zależności i silnej kontroli architektury.

## 2. Problemy, które 1.0 ma rozwiązać

### 2.1 Globalne awarie wywołane lokalnymi zmianami

W dotychczasowym modelu niezależny moduł mógł przez błędną deklarację, problem z kodem, brak klasy lub niezgodność zależności spowodować HTTP 500 całego portalu. W 1.0 jest to uznane za niedopuszczalne.

Docelowa właściwość systemu:

> Uszkodzony moduł może przestać działać, ale Core i niezależne moduły pozostają dostępne.

### 2.2 Rozjeżdżająca się spójność UI

Rozszerzanie modułów i theme powodowało sytuacje, w których nowy element był dodany tylko do jednego szablonu lub miał własną implementację HTML/CSS. W efekcie zmiana theme ujawniała brakujące elementy albo inne zachowanie.

Docelowa właściwość:

> Publiczne UI ma jedno źródło prawdy. Base Theme zapewnia komplet implementacji, a theme potomne tylko nadpisują wybrane elementy.

### 2.3 Reguły zależne od pamięci człowieka/agenta

Jeżeli poprawność architektury wymaga pamiętania o ręcznym wykonaniu kilku kroków, wcześniej czy później zostaną pominięte. Szczególnie dotyczy to pracy agentów AI.

Docelowa właściwość:

> Reguła możliwa do sprawdzenia przez maszynę powinna być sprawdzana przez maszynę.

### 2.4 Duplikacja infrastruktury

Różne funkcje mogą potrzebować tych samych operacji — plików, cache, jobs, realtime, HTTP, audytu, storage. Te funkcje nie powinny implementować infrastruktury ponownie.

Docelowa właściwość:

> Wspólne możliwości są bibliotekami usługowymi z publicznym kontraktem i providerami.

## 3. Charakter produktu

miniPORTAL 1.0 jest platformą składaną z:

- **Core** — kernel aplikacji, kontrakty, lifecycle, routing, auth, permissions, errors, module registry,
- **Libraries** — generyczne usługi wielokrotnego użytku,
- **Providers** — konkretne implementacje usług,
- **Modules** — funkcje domenowe składające istniejące capabilities,
- **UI Core** — semantyczny opis stron i komponentów,
- **Base Theme** — kompletna implementacja renderowania UI,
- **Themes** — style, renderery i layouty nadpisujące Base Theme,
- **Frontend adapters** — htmx/realtime/PWA jako szczegóły implementacyjne.

## 4. UX docelowy

Portal ma sprawiać wrażenie aplikacji natywnej/SPA bez konieczności budowania ciężkiego SPA.

Oczekiwane cechy:

- strony startują szybko nawet na słabszym sprzęcie,
- tabela może wyszukiwać, sortować i filtrować bez pełnego reloadu,
- dashboard może lazy-loadować cięższe fragmenty,
- statusy live przychodzą z serwera bez pollingu co sekundę,
- przejścia między ekranami są płynne,
- na telefonie interfejs zmienia układ komponentów, a nie tylko zmniejsza desktop,
- aplikacja może być instalowana jako PWA,
- brak sieci jest komunikowany przez application shell zamiast pustej strony,
- ciężkie zależności (edytor, terminal, wykresy) nie są pobierane, jeśli nie są potrzebne.

## 5. Filozofia server-driven

Backend pozostaje źródłem prawdy dla stanu i renderowania interfejsu. Preferowany przepływ:

```text
User action
   ↓
HTTP request
   ↓
Controller / Use Case
   ↓
Service contracts
   ↓
UI definition / fragment
   ↓
Renderer + Theme
   ↓
HTML response
   ↓
htmx/browser swaps fragment
```

Nie zakładamy osobnej aplikacji frontendowej rekonstruującej UI z JSON, jeśli nie ma ku temu konkretnego powodu.

## 6. Non-goals

miniPORTAL 1.0 nie ma być:

- frameworkiem PHP ogólnego przeznaczenia,
- odpowiednikiem React/Next/Vue,
- systemem microservices z definicji,
- monolitem, w którym moduł ma dostęp do całego wnętrza Core,
- theme engine opartym wyłącznie na zmianie kolorów,
- marketplace w pierwszym wydaniu,
- systemem, w którym każdy moduł dostarcza własną kopię CSS/JS dla standardowych elementów,
- portalem wymagającym Redis, Node.js build runtime lub WebSocket dla każdej instalacji.

## 7. Kryteria sukcesu 1.0

### Stabilność

- fixture z błędem modułu nie powoduje globalnego HTTP 500,
- błędny manifest jest odrzucany przed aktywacją,
- zła wersja zależności daje czytelny raport, a nie crash,
- rollback jest testowalny i przewidywalny.

### Architektura

- moduły domenowe nie importują internals Core,
- Filesystem API jest współdzielone przez różne domeny,
- capability może mieć więcej niż jednego providera,
- UI modułów nie zna konkretnego theme ani layoutu.

### UI

- Base Theme pokrywa 100% publicznego UI contract,
- dwa znacząco różne theme/layouty renderują tę samą PageDefinition,
- brak override komponentu w theme nie powoduje braku elementu,
- mobile jest traktowany jako pełnoprawny layout, nie jako efekt uboczny media queries.

### Jakość

- jeden command `composer verify` odtwarza wymagane bramki jakości,
- CI blokuje naruszenia architektury,
- UI Catalog obejmuje publiczne komponenty i ich kluczowe stany,
- visual regression wykrywa przypadkowe rozjechanie theme.

### Wydajność

- bazowe widoki nie wymagają ładowania ciężkich bibliotek,
- cache ma jawne polityki prywatności i invalidacji,
- sensitive admin HTML nie trafia do public edge cache,
- dane live nie wymagają agresywnego pollingu.

## 8. Priorytety decyzyjne

W konflikcie celów stosujemy kolejność:

1. bezpieczeństwo i integralność danych,
2. odporność Core,
3. stabilność publicznych contractów,
4. czytelna architektura i testowalność,
5. wydajność runtime,
6. ergonomia programistyczna,
7. efekt wizualny.

„Bajer” UI nie uzasadnia obejścia security boundary, łamania theme contract ani dublowania infrastruktury.