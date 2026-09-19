# Plasma UI concept

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

## Uruchomienie

Z katalogu propozycji:

```bash
python3 -m http.server 8080
```

Następnie otwórz `http://localhost:8080`.

## Ważne

Prototyp jest celowo statyczny. Nie powinien być kopiowany 1:1 do modułów domenowych. Docelowa implementacja miniPORTAL ma korzystać z `PageDefinition`, publicznych komponentów UI, design tokens, renderer registry oraz theme/layout fallback zgodnie z `docs/05-UI-COMPONENT-MODEL.md` i `docs/06-THEMES-LAYOUTS-DESIGN-SYSTEM.md`.
