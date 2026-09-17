# Contributing to miniPORTAL 1.0

miniPORTAL 1.0 jest rozwijany architecture-first. Zmiana, która działa funkcjonalnie, ale obchodzi contract platformy, nie jest poprawną zmianą.

## Przed rozpoczęciem

1. Przeczytaj `README.md` i odpowiedni dokument w `docs/`.
2. Sprawdź `AGENTS.md` — zasady są użyteczne również dla ludzi, mimo że dokument jest szczególnie skierowany do agentów AI.
3. Określ scope zmiany.
4. Jeśli zmieniasz decyzję architektoniczną, przygotuj ADR.

## Workflow

```text
branch
  ↓
implementation + tests + docs
  ↓
composer verify
  ↓
Pull Request
  ↓
required CI
  ↓
review
  ↓
merge
```

Docelowo `main` powinien być chroniony przed bezpośrednim push dla zmian kodowych.

## Małe PR

Preferowane są zmiany o jednym wyraźnym celu. Nie łącz lokalnej poprawki z niepowiązanym refaktorem Core.

Jeżeli poprawna implementacja wymaga zmiany cross-cutting, opisz to jawnie w PR.

## Publiczne API

Zmiana publicznego contractu wymaga oceny kompatybilności i semver. Nie traktuj namespace `Contract` jako zwykłej klasy wewnętrznej.

## UI

Nie dodawaj własnych standardowych button/input/table tylko dlatego, że jest szybciej. Użyj UI API. Nowy generyczny komponent wymaga Base Theme renderer i UI Catalog fixture.

## Infrastruktura

Nie twórz lokalnego filesystem/cache/jobs/security rozwiązania w module. Jeżeli contract nie wystarcza, rozważ rozszerzenie platformy.

## Testy

Dobierz test do ryzyka:

- czysta funkcja → unit,
- implementacja contractu → contract suite,
- kilka warstw → integration,
- UI interakcja → browser,
- zmiana wizualna → visual regression,
- migracja → migration matrix,
- package lifecycle → preflight fixture.

## Dokumentacja

Dokumentacja architektury jest wersjonowana razem z kodem. PR zmieniający zachowanie bez aktualizacji odpowiedniego dokumentu jest niekompletny.

## Commity i historia

Commity powinny opisywać intencję. Nie jest wymagany jeden commit na plik. Dla większego PR ważniejsza jest czytelna historia i możliwość review niż sztuczne rozdrobnienie.

## Bezpieczeństwo

Nie publikuj sekretów, danych produkcyjnych ani stack trace zawierających credentials. Znalezionych podatności nie należy maskować przez UI workaround — popraw boundary backendu.