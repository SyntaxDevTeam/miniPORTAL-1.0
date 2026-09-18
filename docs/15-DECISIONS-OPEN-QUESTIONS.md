# 15 — Decisions and Open Questions

Ten dokument rozdziela decyzje uzgodnione koncepcyjnie od szczegółów, które powinny dostać osobny ADR przed implementacją.

## A. Decyzje zaakceptowane

### D-001 — Server-driven zamiast domyślnego SPA

PHP generuje HTML, a interaktywność jest progresywnie dokładana. Pełne SPA nie jest bazowym modelem miniPORTAL 1.0.

### D-002 — Core domenowo neutralny

Core nie zna Minecraft, VPS, PunisherX ani konkretnych produktów SyntaxDevTeam.

### D-003 — Module jako kompozycja capabilities

Moduły korzystają z publicznych libraries/contracts. Infrastruktura nie jest kopiowana per moduł.

### D-004 — Capability przed konkretnym providerem

Jeśli zależność dotyczy możliwości (np. filesystem), moduł zależy od capability, nie od NodeAgent/SFTP/Local implementation.

### D-005 — Semantyczne UI

Moduły budują PageDefinition/Component Tree, a nie finalny standardowy HTML/CSS.

### D-006 — Base Theme 100% coverage

Każdy publiczny UI component ma renderer w Base Theme. Custom theme ma opcjonalne overrides.

### D-007 — Theme i Layout to odrębne pojęcia

Theme może zawierać layouty, ale layout opisuje rozmieszczenie, a component renderer/design tokens wygląd.

### D-008 — htmx ukryty za UI API

Kod modułu nie powinien być związany z atrybutami htmx.

### D-009 — SSE default dla downstream realtime

WebSocket jest zarezerwowany dla full-duplex use cases. SSE + zwykłe HTTP jest preferowane dla statusów/progress/log stream.

### D-010 — PWA bez cache wrażliwych danych

Service Worker służy do application shell i jawnie bezpiecznych assetów/danych.

### D-011 — Lokalny błąd pozostaje lokalny

Module Dispatcher i lifecycle muszą ograniczać blast radius.

### D-012 — Install != Activate

Package jest stagingowany, testowany i dopiero potem atomowo aktywowany.

### D-013 — Isolated preflight

Kod nowego package jest testowany w osobnym procesie przed produkcyjnym load.

### D-014 — Reguły architektury egzekwuje CI

Dokumentacja nie jest jedynym guardrailem.

### D-015 — Jeden verify command

Docelowy `composer verify` jest lokalnym odpowiednikiem wymaganych quality gates CI.

### D-016 — Lazy heavy frontend

Edytor, terminal, charts i podobne biblioteki nie są częścią bazowego bundle.

### D-017 — APCu default, Redis optional

Minimalna instalacja nie wymaga osobnego Redis. Provider cache pozostaje wymienny.

### D-018 — PHP 8.5 baseline

miniPORTAL 1.0 wymaga PHP 8.5 jako minimalnego runtime. Szczegóły: `docs/adr/0001-php-85-baseline.md`.

### D-019 — Canonical JSON package manifest

Runtime package manifest ma jeden kanoniczny format: `manifest.json`. Discovery nie wykonuje kodu pakietu. Szczegóły: `docs/adr/0002-json-package-manifest.md`.

### D-020 — PHPUnit + PHPStan baseline

Pierwszy quality tooling baseline to PHPUnit 13.x i PHPStan 2.x na maksymalnym poziomie. Narzędzie do architecture dependency rules pozostaje osobną decyzją. Szczegóły: `docs/adr/0003-quality-tooling-baseline.md`.

## B. ADR wymagane przed implementacją odpowiednich milestone'ów

### Q-001 — Dependency Injection container

Opcje:

- lekki własny container,
- istniejąca biblioteka PSR-11.

Kryteria: cold-start, czytelność, compile/cache, brak framework lock-in.

### Q-002 — Router

Czy użyć małej istniejącej biblioteki czy własnego routera? Wymagane: named routes, middleware, module namespace, URL generation.

### Q-003 — Database abstraction

Zakres Core storage: PDO + własne repositories, query builder czy istniejąca lekka warstwa? Nie chcemy pełnego ORM bez uzasadnienia.

### Q-004 — Migration engine

Potrzebne: owner/package ledger, plan, dry-run metadata, reversible flag, isolated tests.

### Q-005 — Manifest format — RESOLVED

Rozstrzygnięte przez `docs/adr/0002-json-package-manifest.md`: runtime używa kanonicznego `manifest.json`.

### Q-006 — Package layout i registry

Symlink `active`, pointer w DB czy kombinacja? Wymagane atomicity i portability.

### Q-007 — Static/architecture tooling — PARTIALLY RESOLVED

`docs/adr/0003-quality-tooling-baseline.md` wybiera PHPStan 2.x dla static analysis. Osobny wybór narzędzia/implementacji architecture dependency rules pozostaje otwarty.

### Q-008 — Test framework — RESOLVED

Rozstrzygnięte przez `docs/adr/0003-quality-tooling-baseline.md`: PHPUnit 13.x jest bazowym frameworkiem testowym.

### Q-009 — Frontend asset pipeline

Czy wystarczy bardzo mały build tool dla hash/minify, czy wykorzystać Vite-like pipeline tylko podczas build? Runtime produkcyjny nie powinien wymagać Node.

### Q-010 — Authentication providers

Local accounts, OAuth/OIDC itp. powinny zostać rozdzielone od centralnej session/authorization warstwy.

### Q-011 — Translation/i18n

Format katalogu tłumaczeń, fallback, pluralization i owner package namespace.

### Q-012 — Background jobs

Czy pierwsza wersja używa DB-backed jobs/CLI worker czy innego lekkiego modelu. Jobs contract ma istnieć niezależnie.

### Q-013 — Realtime server deployment

SSE w PHP-FPM/Apache może wymagać świadomego deployment model. Należy ustalić transport/runtime bez blokowania zwykłych request workers.

### Q-014 — Process isolation po 1.0

Czy stabilne zewnętrzne moduły pozostają in-process, czy pojawi się worker RPC mode dla wyższego poziomu izolacji.

### Q-015 — Package signing

Checksum jest baseline. Podpisy zaufanych publisherów/registry wymagają osobnego threat model i key management.

## C. Jak podejmować ADR

ADR powinien zawierać:

- context/problem,
- constraints,
- considered options,
- decision,
- consequences,
- migration/revisit trigger.

Nie należy blokować całego projektu decyzją, którą można odłożyć do milestone'u, w którym staje się realnie potrzebna.

## D. Decision log policy

Po zatwierdzeniu ADR wpis z sekcji Q może zostać oznaczony jako resolved i wskazywać konkretny plik `docs/adr/NNNN-*.md`. Zmiana decyzji wymaga nowego ADR superseding starego zamiast cichego przepisywania historii.