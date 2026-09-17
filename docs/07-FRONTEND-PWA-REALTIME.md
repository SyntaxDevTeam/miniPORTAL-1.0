# 07 — Frontend, PWA and Realtime

## 1. Strategia frontendowa

miniPORTAL 1.0 pozostaje server-driven MPA/hypermedia application. Nie budujemy osobnej aplikacji SPA jako domyślnego modelu.

Baseline:

- HTML generowany przez PHP,
- nowoczesny native CSS,
- htmx 4.x przez adapter UI,
- mały vanilla JS dla zachowań, których nie pokrywa HTML/CSS/htmx,
- SSE dla server → browser live updates,
- WebSocket dla przypadków wymagających pełnego duplex,
- PWA application shell,
- lazy-loaded heavy components.

## 2. htmx jako szczegół implementacji

Moduły nie powinny emitować atrybutów `hx-*` ręcznie.

Moduł opisuje:

```php
Table::make()
    ->source(route('users.table'))
    ->searchable()
    ->sortable()
    ->lazy();
```

UI adapter może wygenerować odpowiednie mechanizmy htmx.

Korzyść: migracja sposobu interakcji nie wymaga refaktoru wszystkich modułów.

## 3. Pełny request vs fragment request

Każda kluczowa funkcja powinna posiadać logicznie poprawny pełny URL.

Przykład:

```text
/servers/123/files?q=server&sort=name
```

Pełne wejście URL renderuje stronę. Request htmx może otrzymać tylko region tabeli.

Zapewnia to:

- deep links,
- back/forward history,
- możliwość otwarcia w nowej karcie,
- łatwiejszy debugging,
- mniejszą zależność od JS.

## 4. Lazy loading

Elementy mogą ładować się dopiero, gdy są potrzebne:

- widget poniżej fold,
- duża tabela,
- log panel,
- wykres,
- szczegóły pliku.

Lazy load nie może powodować layout shift bez skeleton/min-size placeholder.

## 5. Heavy JS

Nie należy ładować globalnie:

- CodeMirror/Monaco-like editor,
- xterm-like terminal,
- chart library,
- archive explorer,
- drag-and-drop library.

Powinny być importowane dopiero na ekranach, które ich potrzebują.

## 6. SSE jako domyślny realtime downstream

Dla danych typu:

- CPU/RAM,
- liczba graczy,
- status servera,
- progress backupu,
- progress update,
- nowe log lines,
- alerts,

preferujemy SSE, jeśli browser nie musi wysyłać ciągłego strumienia w tym samym połączeniu.

## 7. WebSocket tylko dla full-duplex

Typowe przypadki:

- interaktywny PTY/SSH,
- terminal wymagający strumienia w obu kierunkach,
- ewentualnie bardzo interaktywny protokół, którego SSE + POST nie obsługuje sensownie.

Konsola Minecraft może początkowo działać jako:

```text
server logs → SSE
command submit → POST
```

bez WebSocket.

## 8. Realtime API

Module powinien używać `RealtimeContract`, np. subskrypcji na logiczny channel/job, a nie tworzyć własnego serwera WebSocket.

Transport jest wybierany przez platformę.

## 9. Progressive enhancement

Interfejs powinien zachowywać sens przy:

- wolniejszym JS,
- chwilowym błędzie fragment requestu,
- zerwaniu SSE,
- reduced motion,
- coarse pointer/touch.

Nie każdy ekran musi działać w pełni bez JS, ale krytyczne actions powinny mieć przewidywalny HTTP fallback tam, gdzie jest to praktyczne.

## 10. Nowoczesny CSS

Preferowane możliwości:

- CSS Grid/Subgrid,
- Flexbox,
- Container Queries,
- `:has()`,
- custom properties/design tokens,
- native `<dialog>`/popover tam, gdzie nadają się semantycznie,
- View Transitions,
- scroll-driven animations tylko jako enhancement,
- `content-visibility` dla cięższych obszarów, jeśli pomiary potwierdzą korzyść.

## 11. Efekty reagujące na kursor

Wodotryski są dozwolone, jeśli:

- nie wpływają na czytelność,
- nie wymuszają dużej biblioteki,
- są wyłączone/ograniczone dla coarse pointer,
- respektują `prefers-reduced-motion`,
- wykorzystują transform/opacity/CSS variables zamiast kosztownego layout thrash.

## 12. PWA

PWA składa się z:

- manifestu,
- Service Workera,
- installable metadata,
- application shell,
- offline/error UI.

Application shell może zawierać:

- bazowe CSS,
- logo/ikony,
- podstawowy JS/htmx,
- shell nawigacji,
- stronę offline.

## 13. Czego Service Worker nie powinien cache'ować

Domyślnie nie cache'ujemy trwałe:

- odpowiedzi konsoli,
- listy plików użytkownika,
- treści plików,
- tokenów,
- danych administracyjnych,
- poufnych wyników komend,
- spersonalizowanych fragmentów bez jawnej polityki.

Offline oznacza przede wszystkim, że aplikacja startuje i informuje o braku połączenia, a nie że prywatny panel pozostaje lokalnie w pełnym cache.

## 14. Historia i navigation

Dynamiczne swaps nie mogą zepsuć:

- back/forward,
- canonical URL,
- title,
- focus management,
- scroll restoration.

To część UI adapter contract.

## 15. Frontend budgets

Na etapie beta należy ustalić mierzalne budżety:

- base JS transfer,
- base CSS transfer,
- LCP/INP/CLS target w profilu testowym,
- maksymalny koszt dashboard initial render,
- zakaz globalnego ładowania heavy libs.

Budżety powinny wynikać z pomiarów na reprezentatywnym słabszym urządzeniu, a nie z arbitralnej liczby.