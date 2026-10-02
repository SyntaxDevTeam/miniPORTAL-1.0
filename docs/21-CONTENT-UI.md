# 21 — UI dla treści modułów

`TextAreaField` jest publicznym komponentem UI API dla wielowierszowego
tekstu w formularzach stron i artykułów. Dopuszcza 2–40 widocznych wierszy,
stan wymagany, pomoc i błąd. Base Theme renderuje semantyczne `label` i
`textarea`; wartość i komunikaty są escapowane, a błąd ma `role="alert"` i
`aria-invalid`. Komponent jest w UI Catalog (stan z zawartością); test
kontraktowy sprawdza także próbę wyjścia z `textarea`.

Pole zajmuje dostępną szerokość kontenera i może być powiększane pionowo.
Na wąskich ekranach zachowuje układ jednej kolumny formularza. Plasma używa
tych samych tokenów co pozostałe pola; Base Theme pozostaje fallbackiem.

Publiczny kontrakt jest nowy i addytywny (`minor`); istniejące moduły i
motywy nie wymagają zmian. Wizualna fixture to formularz `Form API` w
`BaseUiCatalog`, obejmujący także pole wielowierszowe.

`RichText` obsługuje treść Markdown oraz stare HTML. Markdown renderuje
CommonMark z wyłączonym surowym HTML i niebezpiecznymi linkami. Renderer HTML
przepuszcza tylko semantyczne elementy tekstu, list, tabel i bezpieczne linki;
odrzuca skrypty, formularze, SVG, style i atrybuty zdarzeń. Media nie są
jeszcze osadzane, ponieważ wymagają osobnego przeniesienia plików i polityki
URL. `BaseUiCatalog` pokazuje oba formaty. Układ `.mp-prose` zawija długi
tekst, a kod i tabele przewijają się poziomo na wąskim ekranie.
