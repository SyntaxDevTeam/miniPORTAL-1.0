# 09 — Security, Authentication and Permissions

## 1. Model zagrożeń

miniPORTAL jest panelem administracyjnym wykonującym potencjalnie destrukcyjne operacje: zarządzanie plikami, procesami, konfiguracją, serwerami i zdalną infrastrukturą. Security boundary ma pierwszeństwo nad wygodą UI.

## 2. Authentication vs Authorization

Authentication odpowiada na „kim jesteś”. Authorization odpowiada na „co możesz zrobić”.

Moduły nie implementują własnej sesji/roli, lecz deklarują permission nodes/actions wykorzystywane przez Core Security.

## 3. Permissions jako centralny contract

Permission powinno mieć:

- namespaced ID,
- opis,
- owner package,
- risk classification opcjonalnie,
- możliwość przypisania do roli/polityki.

Przykład:

```text
minecraft.files.view
minecraft.files.read
minecraft.files.write
minecraft.files.delete
minecraft.console.view
minecraft.console.command
```

## 4. Enforcement w backendzie

Ukrycie przycisku w UI nie jest kontrolą dostępu.

Każda mutująca/read-sensitive operacja ponownie sprawdza permissions na serwerze.

UI może używać permission context do ukrywania niedostępnych actions, ale backend pozostaje źródłem prawdy.

## 5. Resource scopes

Samo permission `files.read` nie wystarcza. Potrzebny jest również resource scope.

Przykład:

```text
user can files.read
AND
resource server = srv_123 allowed
AND
FileScope root = /srv/minecraft/srv_123
```

To zapobiega przeniesieniu identyfikatora w URL do innego serwera.

## 6. CSRF

Wszystkie state-changing browser requests wymagają ochrony CSRF zgodnej z przyjętym session/auth mode.

htmx nie omija tej zasady. Adapter HTTP/UI ma automatycznie dołączać token/context, zamiast wymagać od każdego modułu ręcznej konfiguracji.

## 7. XSS i output escaping

Renderer domyślnie escape'uje tekst. Raw HTML jest osobnym typem wymagającym jawnego użycia i nie powinien przyjmować danych użytkownika bez sanitizacji.

Theme nie może wyłączać globalnej polityki escaping.

## 8. File security

Filesystem scope musi egzekwować granice po stronie API/providerów. Szczegóły w `04-SERVICE-LIBRARIES-FILESYSTEM.md`.

Krytyczne przypadki:

- traversal,
- symlink escape,
- archive zip-slip,
- overwrite conflict,
- executable upload policy,
- niekontrolowany download secrets.

## 9. Secrets

Sekrety:

- nie trafiają do logów,
- nie trafiają do client-side config,
- nie są renderowane w HTML bez jawnej funkcji reveal,
- nie powinny znajdować się w zwykłych exportach config,
- mają centralny access contract.

## 10. Audit

Wysokiego ryzyka akcje powinny pozostawiać audit event:

```text
who
what
target
when
result
source/request id
optional reason/context
```

Audit log nie jest zwykłym debug logiem i powinien mieć inną politykę retencji/dostępu.

## 11. Session security

Należy przewidzieć:

- Secure/HttpOnly cookies,
- SameSite policy,
- session rotation po login/privilege change,
- explicit logout/invalidation,
- idle/absolute timeout zależnie od polityki,
- protection przed session fixation.

Szczegóły konkretnej implementacji zostaną wybrane ADR.

## 12. Rate limiting

Core powinien zapewnić centralną możliwość rate limit dla:

- login/auth endpoints,
- sensitive mutations,
- search endpoints podatnych na kosztowne zapytania,
- command execution,
- package upload/preflight triggers.

## 13. Package security

Package lifecycle powinien chronić przed:

- archive traversal,
- niepoprawnym manifestem,
- nadpisaniem cudzych package IDs,
- wykonaniem kodu przed preflight,
- przypadkowym dostępem staging package do produkcyjnych sekretów.

Podpisy kryptograficzne package są przewidywanym rozszerzeniem po 1.0 lub późnym beta, ale integrity checksum powinien istnieć wcześniej.

## 14. Content Security Policy

Docelowo portal powinien działać z sensowną CSP i bez arbitralnego inline script tam, gdzie to możliwe. Theme/module system musi być zaprojektowany tak, aby nie wymuszał powszechnego `unsafe-inline`.

## 15. Cache security

Dynamiczne/spersonalizowane widoki administracyjne nie mogą trafić do public shared cache bez precyzyjnej polityki cache key/Vary.

Bezpieczny default dla sensitive panel routes:

```text
Cache-Control: private, no-store
```

Publiczne assets z hashami mogą być `public, immutable`.

## 16. Service Worker security

Service Worker nie powinien przechowywać prywatnej zawartości administracyjnej tylko dlatego, że request był GET.

Cache strategies muszą być jawnie allowlistowane.

## 17. Node Agent / remote providers

Zdalny provider musi mieć:

- uwierzytelnienie serwis-serwis,
- najmniejszy potrzebny scope,
- możliwość rotacji credentials,
- timeouty,
- replay/nonce/signature strategy jeśli protokół jej wymaga,
- brak zaufania do path/resource ID od klienta bez walidacji po stronie serwera.

## 18. Security tests

Minimalne klasy testów:

- permission denial,
- cross-resource access denial,
- CSRF rejection,
- XSS escaping,
- path traversal/symlink escape,
- package archive traversal,
- sensitive response cache headers,
- secrets redaction in logs,
- failed auth/rate limit behavior.

## 19. Security jako contract platformy

Moduł nie może „wyłączyć bezpieczeństwa dla wygody”. Jeżeli use case nie mieści się w publicznym security model, należy rozszerzyć model centralnie, a nie tworzyć furtkę lokalną.