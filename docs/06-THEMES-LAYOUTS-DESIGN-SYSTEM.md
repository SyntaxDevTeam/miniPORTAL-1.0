# 06 — Themes, Layouts and Design System

## 1. Problem do rozwiązania

System theme nie może być zbiorem tych samych stron różniących się kolorem. Ma umożliwiać realnie różne kompozycje interfejsu:

- pełna szerokość + pionowy sidebar,
- wyśrodkowany content + sticky horizontal navbar,
- minimalistyczny panel compact,
- layout mobile-first,
- dedykowany layout auth/fullscreen.

Jednocześnie nowy komponent UI nie może wymagać ręcznego dopisania go do każdego theme.

## 2. Rozdzielenie pojęć

### Design Tokens

Wartości wizualne: kolory, spacing, typografia, radius, elevations, motion.

### Component Renderer

HTML/CSS reprezentujący konkretny semantyczny komponent.

### Layout

Szkielet rozmieszczenia głównych regionów strony.

### Theme

Pakiet, który może dostarczać tokens, renderery i kolekcję layoutów.

Theme ≠ layout, choć theme może zawierać własne layouty.

## 3. Base Theme

Base Theme jest elementem platformy, nie opcjonalnym skinem.

Nienaruszalny contract:

> Base Theme implementuje 100% publicznych komponentów UI obsługiwanych przez daną wersję Core/UI API.

Jeśli komponent publiczny `DatePicker` zostanie dodany do UI API, jego minimalny poprawny renderer trafia najpierw do Base Theme.

Implementowany `RendererRegistry` mapuje dokładną klasę komponentu na renderer,
odrzuca podwójną rejestrację i jawnie zgłasza brak renderera. `BaseTheme`
rejestruje pełny aktualny katalog publicznych komponentów, a test kontraktowy
porównuje ten katalog z listą rendererów. Zagnieżdżone komponenty są renderowane
rekurencyjnie przez ten sam registry, bez obchodzenia fallbacku.

## 4. Theme inheritance i fallback

Resolver renderera:

```text
Active Theme exact override?
  yes → render
  no
    ↓
Parent Theme override?
  yes → render
  no
    ↓
Base Theme renderer
```

Dzięki temu stary theme po aktualizacji Core nadal potrafi wyrenderować nowy publiczny komponent przez Base Theme, o ile jego manifest deklaruje kompatybilność z wersją UI API.

## 5. Struktura theme — propozycja

```text
themes/my-theme/
├── theme.json
├── tokens/
│   ├── colors.css
│   ├── spacing.css
│   └── typography.css
├── components/
│   ├── button.php
│   ├── table.php
│   └── card.php
├── layouts/
│   ├── dashboard.php
│   ├── centered.php
│   ├── auth.php
│   └── fullscreen.php
└── assets/
    ├── theme.css
    └── theme.js
```

Theme nie musi mieć pliku dla każdego komponentu.

## 6. Layout contract

PageDefinition wybiera layout po semantycznej nazwie/capability, nie po ścieżce template.

Przykładowe layout roles:

- `application`,
- `dashboard`,
- `auth`,
- `fullscreen`,
- `minimal`,
- `error`.

Theme mapuje role na konkretne layouty.

Możliwe jest posiadanie wielu wariantów i ustawienie preferencji użytkownika/administratora, ale moduł nie powinien wymuszać np. `left-sidebar.php`.

## 7. Layout regions

Wspólny model regionów może obejmować:

```text
header
primary_navigation
secondary_navigation
breadcrumbs
page_header
actions
content
aside
footer
overlays
```

Nie każdy layout musi wyświetlać każdy region w tym samym miejscu. Brak regionu powinien mieć zdefiniowany fallback/merge behavior.

## 8. Design tokens

Core/UI definiuje semantyczne token names, np.:

```css
--mp-color-surface-1
--mp-color-surface-2
--mp-color-text
--mp-color-muted
--mp-color-primary
--mp-color-danger
--mp-space-xs
--mp-space-sm
--mp-space-md
--mp-radius-sm
--mp-radius-md
--mp-font-body
--mp-font-heading
--mp-motion-fast
```

Theme przypisuje wartości.

Komponent domenowy nie powinien używać literalnego `#123456`, jeśli istnieje semantyczny token.

## 9. Responsywność komponentowa

Preferujemy Container Queries dla komponentów wielokrotnego użytku. Komponent reaguje na dostępne miejsce zamiast zakładać, że `viewport <= 768px` zawsze oznacza ten sam układ.

Dzięki temu `ServerCard` może działać w:

- szerokim dashboardzie,
- wąskim aside,
- telefonie,
- osadzonym panelu.

## 10. Różny DOM jest dozwolony

Theme może wyrenderować tę samą semantykę w znacząco inny DOM, jeśli zachowuje contract funkcjonalny i accessibility.

Przykład `DataTable`:

- desktop theme: `<table>`,
- mobile/compact renderer: lista kart z label-value.

Nie należy jednak zmieniać semantyki action/permissions/data tylko z powodu theme.

## 11. Theme manifest

Powinien deklarować m.in.:

```json
{
  "schema": 1,
  "id": "syntax-dark",
  "version": "1.0.0",
  "requires": {
    "uiApi": "^1.0"
  },
  "extends": "base",
  "layouts": ["application", "auth", "fullscreen"]
}
```

Może też deklarować experimental overrides, preferred color scheme i capabilities prezentacyjne.

## 12. Theme Contract Tests

Każdy theme przechodzi automatycznie:

- manifest validation,
- UI API compatibility,
- layout role validation,
- renderer boot,
- render UI Catalog,
- required asset checks,
- keyboard/accessibility smoke tests,
- desktop/mobile browser render,
- no-fatal-errors.

Raport może wyglądać:

```text
Theme syntax-dark 2.4.0
Overrides: 18/52
Inherited: 34/52
UI API: 1.x compatible
Layouts: application, auth, fullscreen
Contract tests: PASS
```

## 13. Brak „synchronizacji theme” jako ręcznej czynności

Dodanie publicznego komponentu do Core nie generuje zadania „dopisz do wszystkich theme”.

Obowiązkowe jest:

1. UI contract,
2. Base Theme renderer,
3. UI Catalog fixture,
4. test.

Pozostałe theme mogą dodać override później.

## 14. Assets

Theme assets powinny być wersjonowane/hashowane i możliwe do cache'owania długoterminowo.

Theme JS ma być minimalny i prezentacyjny. Logika domenowa nie trafia do theme JS.

## 15. Bezpieczny fallback aktywnego theme

Jeżeli aktywny theme nie może wystartować po aktualizacji, Core powinien móc przełączyć się na Base Theme/emergency layout zamiast zwrócić globalny 500.

Błąd Theme jest lokalnym błędem warstwy prezentacji, a nie powodem utraty panelu administracyjnego.
