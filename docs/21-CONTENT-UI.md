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
