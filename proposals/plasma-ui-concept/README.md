# Plasma UI concept — HTML/CSS/JS/HTMX

Eksperymentalna propozycja kierunku wizualnego dla miniPORTAL 1.0.

Ten katalog **nie jest częścią produkcyjnego Theme Engine ani UI Core**. Służy jako materiał referencyjny do późniejszego przeniesienia zatwierdzonych założeń do semantycznego UI API, Base Theme oraz referencyjnego theme zgodnie z dokumentacją projektu.

## Warianty

- `public-collapsed.html` — publiczny widok z kompaktowym sidebarem opartym na ikonach,
- `public-expanded.html` — ten sam widok z rozwiniętą nawigacją,
- `admin.html` — propozycja Panelu Administratora,
- `index.html` — prosty launcher do wszystkich wariantów.

## Kierunek wizualny

- inspiracja KDE Plasma 6 bez kopiowania konkretnego motywu,
- matowe szkło dla sidebara, topbara i stopki,
- półprzezroczyste powierzchnie i `backdrop-filter`,
- subtelne niebiesko-fioletowe smugi światła w hero,
- spokojniejsze, mniej rozpraszające tło pod główną treścią,
- semantyczne kolory statusów,
- zwijany sidebar,
- responsywność i `prefers-reduced-motion`,
- lekki JavaScript bez frameworka SPA.

## Interakcje demonstracyjne

- zwijany sidebar z osobnym stanem dla widoku publicznego i panelu,
- mobilny drawer z backdropem i obsługą klawiatury,
- wyszukiwarka otwierana przez `Ctrl/Cmd + K`, której wyniki są pobierane jako
  fragment HTML przez HTMX,
- szczegóły health checku panelu ładowane przez HTMX bez przeładowania strony,
- tryb ograniczonych efektów świetlnych pod przyciskiem słońca,
- aktualny zegar panelu, przywracanie fokusu i komunikaty `aria-live`,
- fallback do zwykłego HTML/CSS, gdy JavaScript albo HTMX są niedostępne.

Prototyp przypina HTMX 2.0.10 z jsDelivr wraz z SRI zgodnie z oficjalnym sposobem
instalacji. Docelowy Theme Engine powinien dostarczać zależność lokalnie i ukrywać
atrybuty transportowe za UI API; moduły nie będą wpisywały `hx-*` bezpośrednio.

## Uruchomienie

Z katalogu propozycji:

```bash
python3 -m http.server 8080
```

Następnie otwórz `http://localhost:8080`. Serwer HTTP jest wymagany dla fragmentów
HTMX — otwarcie plików bezpośrednio przez `file://` pokaże layout, lecz przeglądarka
może zablokować pobieranie partiali.

## Ważne

Prototyp jest celowo statyczny. Nie powinien być kopiowany 1:1 do modułów domenowych. Docelowa implementacja miniPORTAL ma korzystać z `PageDefinition`, publicznych komponentów UI, design tokens, renderer registry oraz theme/layout fallback zgodnie z `docs/05-UI-COMPONENT-MODEL.md` i `docs/06-THEMES-LAYOUTS-DESIGN-SYSTEM.md`.
