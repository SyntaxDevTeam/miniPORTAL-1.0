# 05 — UI Component Model

## 1. UI jako kontrakt semantyczny

Moduł nie powinien tworzyć finalnego HTML standardowych elementów interfejsu. Powinien opisywać **co** chce pokazać i **jakie zachowanie** ma mieć element. Renderer i aktywny Theme decydują, jak zostanie to przedstawione.

Przepływ:

```text
Module
  ↓
PageDefinition
  ↓
UI Component Tree
  ↓
Renderer Registry
  ↓
Theme override or Base Theme fallback
  ↓
Layout
  ↓
HTML
```

## 2. PageDefinition

Strona jest obiektem/danym, nie plikiem HTML.

Przykład konceptualny:

```php
return Page::make('server-files')
    ->title('Pliki')
    ->layout('dashboard')
    ->breadcrumb('Serwery', route('servers.index'))
    ->breadcrumb($server->name())
    ->breadcrumb('Pliki')
    ->content(
        FileBrowser::make($scope)
            ->searchable()
            ->allowUpload($permissions->can('files.upload'))
    );
```

PageDefinition nie może zakładać, że layout ma sidebar po lewej albo navbar na górze.

Implementowany baseline `PageDefinition` zawiera semantyczny layout role,
nazwane regiony, breadcrumbs i deklaratywne actions. Drzewo komponentów ma
stabilne logiczne identity niezależne od DOM; walidator odrzuca cykle oraz
duplikaty identity pomiędzy regionami. URL akcji/nawigacji jest ograniczony do
ścieżek względnych lub HTTPS, a intencja usunięcia zawsze wymaga potwierdzenia.
Kontrakt nie emituje HTML i nie zakłada struktury konkretnego layoutu.

## 3. Component Tree

UI można modelować jako drzewo:

```text
Page
├ Header
│ ├ Title
│ └ Actions
│   ├ Button
│   └ Dropdown
├ Toolbar
│ ├ SearchInput
│ └ FilterGroup
└ DataTable
  ├ Column
  ├ Column
  └ Pagination
```

Komponent może zawierać inne komponenty, ale powinien mieć jasno określone contract props/children.

## 4. Publiczne komponenty bazowe

Minimalny katalog publicznego UI API powinien objąć:

### Typografia i status

- Heading,
- Text,
- Code/Monospace,
- Badge,
- StatusBadge,
- Alert,
- EmptyState,
- ErrorState,
- LoadingState.

### Actions

- Button,
- ButtonGroup,
- LinkAction,
- Dropdown,
- ContextMenu,
- CommandBar/Toolbar.

### Forms

- Form,
- Field,
- TextInput,
- Textarea,
- Select,
- MultiSelect,
- Checkbox,
- Radio,
- Switch,
- Slider,
- FileInput,
- validation message,
- submit/loading state.

### Dane

- Table/DataTable,
- Column,
- Pagination,
- Sort,
- Filters,
- KeyValueList,
- DescriptionList,
- Progress,
- Metric.

### Struktura

- Card,
- Panel,
- Stack,
- Cluster,
- Grid,
- Divider,
- Tabs,
- Accordion,
- Modal/Dialog,
- Drawer.

### Nawigacja

- Navbar,
- Sidebar navigation model,
- Breadcrumbs,
- Pagination,
- Stepper.

Lista nie jest zamknięta, ale dodanie publicznego komponentu wymaga procesu opisanego w testach/theme contract.

Zaimplementowany baseline publicznego API obejmuje `Heading`, `Text`, `Alert`,
`Stack`, `Card`, `Form`, `TextField`, `SelectField`, `CheckboxField`,
`LoadingState`, `EmptyState`, `ErrorState`, `DataTable` i `Pagination`. Każdy
komponent jest niemutowalnym obiektem semantycznym, ma renderer Base Theme oraz
reprezentatywny stan w `BaseUiCatalog`. Renderery centralnie escapują tekst,
zachowują poziom heading, role live dla stanów dynamicznych i nie przyjmują
surowego HTML od modułu.

## 5. Form API

Form powinien rozdzielać:

- schema pól,
- wartości,
- validation,
- errors,
- actions,
- sposób renderowania.

Przykład:

```php
Form::make('server-settings')
    ->field(TextField::make('name')->label('Nazwa')->required())
    ->field(SelectField::make('version')->options($versions))
    ->field(SwitchField::make('auto_restart'))
    ->submit(Action::post('save', route('server.settings.save')));
```

Theme może wyrenderować form pionowo, dwukolumnowo lub w stylu compact settings bez zmiany definicji.

Zaimplementowany baseline Form API obsługuje metody GET/POST, bezpieczny action,
typy text/email/password/url/search, wartości, required, help, błędy walidacji,
select oraz checkbox. Formularz POST jest odrzucany bez tokenu CSRF, a renderer
Base Theme generuje semantyczne etykiety, `aria-required`, `aria-invalid` i
komunikat błędu z rolą alertu. Pole hasła nie może otrzymać wartości początkowej.
Rozdzielenie schema/value binding i walidacja serwerowa będą kolejną warstwą;
komponenty nie traktują danych wejściowych jako zaufanego HTML.

## 6. Table API

Tabela jest częstym źródłem duplikacji. Publiczny Table contract powinien wspierać:

- columns,
- row identifiers,
- server-side pagination,
- sort,
- filters,
- search,
- row actions,
- bulk actions,
- empty/loading/error states,
- responsive representation.

Na mobile Theme może zmienić tabelę w zestaw kart, jeśli zachowane są semantyka i dostęp do tych samych działań.

Baseline `DataTable` posiada jawny zestaw `TableColumn`, waliduje unikalność
kolumn i zgodność każdego wiersza ze schematem, nie przyjmuje surowego HTML w
komórkach oraz wymaga jawnego `EmptyState`, gdy wynik jest pusty. `TableRow`
umożliwia nadanie stabilnego, bezpiecznego identyfikatora wiersza; dla zgodności
proste tablice skalarów nadal są przyjmowane jako anonimowe wiersze.

Server-driven query API obejmuje `TableQueryControls`: wyszukiwanie i filtry są
zwykłym formularzem GET, mogą zachowywać jawnie wskazane parametry zapytania, a
sortowanie jest deklarowane per `TableColumn` jako bezpieczny URL i opcjonalny
`SortDirection`. Renderer Base Theme generuje semantyczny `<table>`,
`scope="col"`, `aria-sort`, caption i centralny escape wartości. `Pagination`
może być częścią `DataTable` i opisuje bieżącą/łączną liczbę stron oraz
bezpieczne linki poprzednia/następna.

Źródło danych jako osobny kontrakt oraz row/bulk actions pozostają kolejnym
etapem. Mutujące akcje tabeli nie mogą być dodane jako przypadkowe linki;
kontrakt musi uwzględnić metodę żądania, CSRF i confirmation semantics.

## 7. Actions i Intents

Zamiast ręcznego HTML + `onclick`, UI powinno opisywać action/intencję:

```text
GET navigation
POST mutation
DELETE confirmation
open dialog
refresh fragment
start job
```

Adapter frontendowy decyduje, czy action zostanie wykonana pełnym requestem czy htmx fragment requestem.

## 8. Loading, empty i error to pełnoprawne stany

Każdy data-driven component powinien jawnie obsługiwać:

- loading,
- success,
- empty,
- partial/degraded,
- error,
- permission denied.

Nie należy dodawać ich ad hoc per module.

`LoadingState`, `EmptyState`, `ErrorState`, `PermissionDeniedState` i
`DegradedState` są częścią publicznego kontraktu. Base Theme nadaje im
odpowiednie role accessibility (`status`/`alert`), a `ErrorState` może pokazać
bezpieczny publiczny error ID bez ujawniania szczegółu technicznego.
`PermissionDeniedState` może opcjonalnie ujawnić wyłącznie bezpieczny identyfikator
wymaganego uprawnienia, natomiast `DegradedState` może zawierać nadal dostępne
komponenty zamiast sprowadzać częściową awarię do pustego ekranu.

## 9. Fragment rendering

UI Renderer powinien umieć wyrenderować:

- full page,
- named region,
- single component,
- out-of-band update,
- error replacement.

Dzięki temu htmx/SSE korzystają z tego samego drzewa UI zamiast osobnych template'ów.

Bazowy kontrakt `UiRenderer` i implementacja `ThemeUiRenderer` obsługują już
pełną stronę, nazwany region oraz pojedynczy komponent. Renderowanie fragmentu
używa registry aktywnego theme wraz z całym łańcuchem dziedziczenia, więc np.
komponent odziedziczony z Base Theme może nadal zawierać zagnieżdżony override
aktywnego theme. Mechanika out-of-band update, wybór target/swap oraz error
replacement pozostają odpowiedzialnością przyszłego adaptera interakcji (htmx /
Realtime), a nie modułów domenowych.

## 10. Component identity

Komponenty dynamiczne potrzebują stabilnego logical ID, ale moduł nie powinien ręcznie składać losowych DOM ID. UI Core może generować DOM identity na podstawie component tree/request context.

## 11. Custom components modułu

Nie każda domena daje się wyrazić bazowymi komponentami. Dopuszczamy komponenty domenowe, ale:

- moduł dostarcza ich contract,
- moduł musi dostarczyć domyślny renderer korzystający z design tokens/base primitives,
- theme może opcjonalnie override'ować renderer,
- brak theme override nigdy nie oznacza braku UI,
- custom component nie może wymagać ręcznej edycji wszystkich istniejących theme.

## 12. UI Catalog

Core powinien generować developerski katalog wszystkich komponentów i stanów, np. `/__dev/ui` wyłącznie w odpowiednio zabezpieczonym środowisku.

Catalog pokazuje:

```text
Button: primary/secondary/danger/disabled/loading/icon
Input: empty/filled/invalid/disabled/readonly
Table: empty/loading/error/paged/actions
Progress: 0/25/50/100/indeterminate
Modal: normal/destructive/scrolling
```

Można przełączać aktywny theme i viewport.

Catalog jest źródłem fixture dla visual regression.

## 13. Accessibility baseline

Renderer bazowy musi dbać o:

- semantyczny HTML,
- label/form associations,
- keyboard navigation,
- focus visibility,
- dialog focus handling,
- odpowiedni status/aria live dla dynamicznych zmian,
- `prefers-reduced-motion`,
- nieopieranie statusu wyłącznie na kolorze.

Theme może zmieniać wygląd, ale nie może łamać tych gwarancji bez wykrycia przez testy.

## 14. Zasada przyszłościowa

Publiczne UI API jest stabilniejszym contractem niż HTML. HTML theme może zmieniać się intensywnie, ale moduły nie powinny wymagać refaktoru tylko dlatego, że nowy theme używa zupełnie innego DOM.
