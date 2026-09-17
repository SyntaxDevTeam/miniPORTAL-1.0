# Dokumentacja miniPORTAL 1.0

Dokumenty są numerowane według zależności poznawczej: najpierw cel i architektura, potem kontrakty, prezentacja, odporność, bezpieczeństwo i proces wytwarzania.

## Fundament

- `00-PRODUCT-VISION.md` — cele produktu, non-goals i kryteria sukcesu.
- `01-ARCHITECTURE.md` — warstwy, przepływy i dependency rules.
- `02-CORE-KERNEL.md` — odpowiedzialności Kernel/Core.

## Rozszerzalność i usługi

- `03-MODULES-DEPENDENCIES-CAPABILITIES.md` — moduły, dependency resolver, capabilities i lifecycle.
- `04-SERVICE-LIBRARIES-FILESYSTEM.md` — biblioteki usługowe, szczegółowy model Filesystem API i providers.

## UI i frontend

- `05-UI-COMPONENT-MODEL.md` — PageDefinition, komponenty, formularze, tabele, actions i fragmenty.
- `06-THEMES-LAYOUTS-DESIGN-SYSTEM.md` — Base Theme, override, layouty, design tokens i fallback.
- `07-FRONTEND-PWA-REALTIME.md` — htmx, SSE, WebSocket, PWA, lazy loading i progressive enhancement.

## Niezawodność i bezpieczeństwo

- `08-RESILIENCE-UPDATE-ROLLBACK.md` — staging, preflight, activation, module boundary, health i rollback.
- `09-SECURITY-AUTH-PERMISSIONS.md` — auth, permissions, scopes, CSRF, secrets, audit i cache bezpieczeństwa.

## Jakość i proces

- `10-TESTING-CI-QUALITY-GATES.md` — `composer verify`, contract tests, architecture tests, browser/visual tests.
- `11-AI-AGENT-GOVERNANCE.md` — zasady projektowe szczególnie ważne przy pracy agentów AI.
- `12-PERFORMANCE-CACHE-OBSERVABILITY.md` — cache, wydajność, logowanie, metryki i diagnostyka.
- `13-VERSIONING-COMPATIBILITY-RELEASES.md` — semver, API compatibility, migracje i kanały wydań.
- `14-REFERENCE-CONTRACTS-EXAMPLES.md` — przykładowe manifesty i pseudokod kontraktów.
- `15-DECISIONS-OPEN-QUESTIONS.md` — zaakceptowane decyzje architektoniczne i tematy wymagające ADR.

## Dokumenty z korzenia

- [`../README.md`](../README.md) — szybki opis projektu.
- [`../ROADMAP.md`](../ROADMAP.md) — milestone'y od specification freeze do 1.0.
- [`../AGENTS.md`](../AGENTS.md) — nadrzędne reguły dla agentów i automatycznych zmian.

## Zasada aktualizacji dokumentacji

Dokumentacja jest częścią contractu projektu. Zmiana zachowania publicznego API, granicy architektonicznej, lifecycle pakietu, wymagania theme lub reguły bezpieczeństwa musi aktualizować odpowiedni dokument w tym samym PR. Dla decyzji, których nie da się opisać jako zwykłe doprecyzowanie, należy dodać ADR.