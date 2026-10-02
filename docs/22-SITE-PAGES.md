# 22 — Pierwszy opcjonalny moduł stron

`site.pages` jest wyłączalnym modułem w `modules/SitePages`. Dostarcza własną
migrację tabeli, listę i widok opublikowanych stron oraz edytor
`/modules/site.pages/admin`. Zapis wymaga sesji z `pages.manage` (lub `*`),
ważnego CSRF i walidacji tytułu, sluga, formatu, treści i statusu. Szkic nie
jest zwracany przez publiczny endpoint. Moduł korzysta z `ModuleFactory`,
`ModuleServices`, kontraktu `Database` i UI API; Core nie zawiera logiki stron.

Moduł rejestruje publiczną i administracyjną akcję nawigacji. `NavigationCatalog`
udostępnia akcje tylko aktywnych właścicieli, a trasy również sprawdzają
aktywny wskaźnik przy każdym żądaniu. Na stronie głównej i w panelu linki
pochodzą z tego kontraktu, więc wyłączenie modułu usuwa je bez zmiany Core.

Treść w formacie Markdown jest renderowana z wyłączonym surowym HTML i
niebezpiecznymi URL. Starszy HTML jest filtrowany do semantycznych elementów;
skrypty, formularze, style, SVG i atrybuty zdarzeń są usuwane. Obrazy czekają
na osobną migrację plików i politykę URL. Oryginalna treść zostaje w bazie.

Migracja ze starego VPS jest osobna od instalacji modułu. Eksporter
`bin/legacy-export-pages.php` wykonuje tylko odczyt. `legacy:pages:plan` i
`legacy:pages:apply CHECKSUM` wymagają pustej docelowej tabeli stron, wcześniej
przeniesionych autorów i identycznego snapshotu. Stare ID trafia do
`legacy_id`, a nowy `author_id` używa tego samego mapowania co import
tożsamości. Import zachowuje slug, treść, format, status i czas utworzenia oraz
aktualizacji po przeliczeniu do UTC. Ponowny import jest blokowany.

Realny snapshot z 2026-10-02 zawiera 10 opublikowanych stron (8 Markdown,
2 HTML). Został sprawdzony na izolowanej bazie SQLite: plan i wykonanie
przeniosły 10/10. Produkcyjne zastosowanie wymaga najpierw ustabilizowania
osobnego checkoutu pod domeną `new.syntaxdevteam.pl`, backupu aktualnej bazy,
instalacji modułu i kontroli sumy snapshotu. Artykuły, sekcje strony głównej,
media i projekty nie należą do tej migracji.

Zmiany w UI API (`TextAreaField`, `RichText`), module factory i nawigacji są
addytywne (`minor`). Motywy nadal używają Base Theme jako fallbacku. Testy
obejmują SQLite, lifecycle nawigacji, autoryzację i CSRF, ukrycie szkicu,
bezpieczne renderowanie oraz importer.
