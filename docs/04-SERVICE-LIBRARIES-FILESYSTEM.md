# 04 — Service Libraries and Filesystem API

## 1. Cel bibliotek usługowych

Library dostarcza wspólną, generyczną możliwość. Moduły korzystają z niej przez contract zamiast implementować tę samą infrastrukturę wielokrotnie.

Kandydaci:

- Filesystem,
- Cache,
- Database/Storage,
- HTTP Client,
- Jobs/Queue,
- Realtime,
- Archive,
- Audit,
- Notifications,
- Secrets/Credentials access,
- Clock/ID generation.

## 2. Contract + Provider

Schemat:

```text
Module
  ↓
FilesystemContract
  ↓
Provider selected by Composition Root
  ├ Local
  ├ SFTP
  └ Node Agent
```

Provider implementuje zachowanie, ale nie zmienia semantyki contractu.

## 3. Filesystem API jako pierwsza biblioteka referencyjna

Filesystem jest dobrym contractem referencyjnym, ponieważ wykorzystają go różne domeny:

- pliki konkretnego serwera Minecraft,
- pliki zdalnego VPS,
- backup browser,
- import/export,
- edycja konfiguracji.

Każda z nich powinna używać tego samego modelu operacji i błędów.

## 4. Zakres funkcjonalny Filesystem

Docelowe API powinno zapewniać co najmniej:

```text
list(path)
stat(path)
exists(path)
read(path)
streamRead(path)
write(path)
streamWrite(path)
createFile(path)
createDirectory(path)
copy(source, target)
move(source, target)
rename(path, name)
delete(path)
upload(...)
download(...)
search(...)
searchContent(...)
checksum(path)
metadata(path)
```

Operacje zaawansowane mogą być capabilities podrzędnymi, np. `content-search`, `archive`, `watch`.

## 5. FileScope

Najważniejszym elementem bezpieczeństwa jest scope.

Module nie dostaje pełnego filesystem providera. Dostaje ograniczony widok:

```php
$scope = $filesystem->scope(
    root: '/srv/minecraft/survival',
    policy: FilePolicy::builder()
        ->read(true)
        ->write(true)
        ->delete(true)
        ->maxUploadBytes(536870912)
        ->build()
);
```

Dalsze operacje są względne do scope:

```php
$scope->list('/plugins');
$scope->read('/server.properties');
```

## 6. Nienaruszalne właściwości scope

Provider/contract musi chronić przed:

- `../` path traversal,
- absolutnym path escape,
- symlink escape,
- race w canonicalization tam, gdzie ma znaczenie,
- dostępem poza root po `move/copy`,
- wykorzystaniem case sensitivity/case folding do obejścia polityki,
- niedozwolonymi typami plików, jeśli policy je ogranicza,
- oversize upload,
- operacją zabronioną przez permission/policy.

Scope nie może polegać wyłącznie na frontendzie ukrywającym `..`.

## 7. Policy

Policy jest obiektem danych/contractu. Może definiować:

- read/write/delete/create,
- maksymalny upload/download,
- allowed/denied extensions,
- hidden files visibility,
- symlink policy,
- search policy,
- max recursive depth,
- quota,
- read-only mode,
- atomic write requirement.

Permissions użytkownika i FilePolicy są osobnymi warstwami. Operacja wymaga przejścia obu.

## 8. Provider capabilities

Nie każdy provider musi wspierać wszystko jednakowo.

Przykład:

```text
LocalProvider:
  random-access-read ✔
  content-search     ✔
  atomic-rename      ✔

SftpProvider:
  random-access-read depends
  content-search     expensive/optional
  atomic-rename      server dependent
```

Provider publikuje capability metadata. UI może na tej podstawie ukryć/wyłączyć funkcję semantycznie, bez hardkodowania `if provider == SFTP`.

## 9. Stabilny model błędów

Filesystem contract mapuje błędy implementacji na własną taxonomy:

- `PathNotFound`,
- `AlreadyExists`,
- `AccessDenied`,
- `ScopeViolation`,
- `UnsupportedOperation`,
- `QuotaExceeded`,
- `ProviderUnavailable`,
- `Conflict`,
- `IoFailure`.

Moduł nie interpretuje tekstu wyjątku z biblioteki SFTP.

## 10. Atomic write

Dla edycji konfiguracji preferowany model:

```text
write temp
  ↓
fsync/flush if meaningful
  ↓
validate optional
  ↓
atomic replace/rename
```

Ma to ograniczać uszkodzenie pliku przy przerwaniu requestu.

## 11. Audit hooks

Mutujące operacje powinny emitować audit events:

```text
actor
scope id
operation
logical path
result
timestamp
correlation id
```

Nie należy logować treści pliku ani sekretów domyślnie.

## 12. Przykład dwóch modułów korzystających z tego samego API

### Minecraft Files

```text
scope root = server working directory
policy = no escape; server-specific permissions
provider = NodeAgent or Local
```

### VPS Files

```text
scope root = configured admin directory
policy = possibly broader/read-only per role
provider = SFTP or NodeAgent
```

Oba mogą użyć tego samego `FileBrowser` UI Component i FilesystemContract.

## 13. Contract tests providerów

Każdy provider musi przejść wspólny test suite:

- list/read/write lifecycle,
- nested directories,
- rename/move/copy,
- denied scope escape,
- denied symlink escape,
- error mapping,
- concurrent/atomic behavior w zadeklarowanym zakresie,
- cleanup po błędzie.

Dopiero provider, który przechodzi contract tests, może deklarować daną wersję `filesystem` capability.

## 14. Implementowany CacheContract baseline

Pierwsza biblioteka Milestone 2 używa publicznego `Cache` contractu pod namespace `SyntaxDevTeam\MiniPortal\Library\Cache\Contract`.

Baseline obejmuje:

- `get`, `has`, `set`, `delete`,
- `remember` z producerem wykonywanym tylko przy cache miss,
- opcjonalny TTL,
- semantykę `ttl <= 0` jako brak retencji/usunięcie wpisu,
- cache wartości `null` rozróżnialny od cache miss poprzez `has`,
- namespaced scopes dla izolacji kluczy package/module.

Providerzy:

- `ArrayCache` — bezpieczny fallback lokalny dla bieżącego procesu/requestu,
- `NullCache` — jawny provider bez retencji, przydatny do testów i diagnostyki,
- `ApcuCache` — preferowany provider single-node, jeśli APCu jest dostępne i aktywne dla bieżącego SAPI.

Composition Root publikuje aktywny cache jako capability `cache@1.0.0`. Moduły mogą zależeć od publicznego contractu, ale architecture guardrail blokuje zależność od klas `Provider` i `Support`.

APCu pozostaje opcjonalne: brak rozszerzenia nie może zablokować uruchomienia Core. W takim środowisku wybierany jest `ArrayCache`, co daje cache lokalny, ale nie współdzielony pomiędzy workerami.


## 15. Implementowany FilesystemContract baseline

Milestone 2 posiada teraz pierwszy provider `filesystem@1.0.0` dla lokalnego systemu plików.

Publiczne API dla kodu modułowego opiera się na `ScopedFilesystem` i logicznym `Path`. Moduł nie powinien otrzymywać bezpośrednio `LocalFilesystemProvider`; provider pozostaje detalem Composition Root i jest blokowany przez architecture guardrail.

Baseline `ScopedFilesystem` obejmuje:

- `exists`,
- `stat`,
- deterministyczne `entries`,
- `read`,
- atomic `write`,
- `createDirectory`,
- file `copy`,
- `move`,
- bezpieczne `delete` bez domyślnej rekursji.

Model bezpieczeństwa:

- `Path` jest zawsze ścieżką logiczną względem scope,
- segment `..` i backslash escape są odrzucane przed providerem,
- Local provider kanonikalizuje root przez `realpath`,
- root będący symlinkiem jest odrzucany,
- każdy komponent symlinkowy na ścieżce operacji jest blokowany,
- wynik `realpath` istniejącego pliku musi pozostać wewnątrz root,
- target mutacji jest budowany dopiero po bezpiecznym rozwiązaniu istniejącego parent directory,
- `move/copy` walidują osobno source i target,
- scope root nie może być usunięty ani przeniesiony.

Atomic write używa pliku tymczasowego w katalogu docelowym i publikuje zmianę przez `rename`. Analogicznie file copy jest najpierw wykonywany do pliku tymczasowego, a dopiero potem publikowany.

`FilePolicy` oddziela prawa read/write/create/delete od uprawnień użytkownika. Wspiera także maksymalny rozmiar pojedynczego zapisu. Błąd providera jest mapowany na stabilną taxonomy Filesystem zamiast przeciekać jako surowy warning/tekst systemowy.

Wspólny contract suite pokrywa lifecycle read/write/stat/list/copy/move/delete, a testy bezpieczeństwa obejmują traversal, symlink escape, read-only policy, write quota i brak pozostawionych temporary files po poprawnym atomic replace.

Aktualny Local provider świadomie nie implementuje jeszcze:

- recursive directory copy/delete,
- streaming,
- content search,
- checksum,
- upload/download abstractions,
- race-free descriptor-based traversal dla wszystkich platform.

Te elementy będą dodawane jako kolejne, jawnie testowane rozszerzenia contractu zamiast rozmywać baseline `filesystem@1.0.0`.


## 16. Implementowany Database/Storage baseline

Q-003 został rozstrzygnięty przez `ADR-0007`. Publiczne API Storage jest małą warstwą SQL zamiast wystawiania PDO albo wprowadzania pełnego ORM.

`Database` zapewnia:

- `fetchOne(SqlStatement)`,
- `fetchAll(SqlStatement)`,
- `execute(SqlStatement)`,
- jawną `transaction(callback)`,
- `isInTransaction()`.

`SqlStatement` rozdziela tekst SQL od parametrów. Provider PDO binduje wartości osobno; repozytoria nie powinny interpolować danych użytkownika do SQL.

`StorageNamespace` wyprowadza deterministyczny, bezpieczny prefix tabeli z package ID, a `SqlIdentifier` waliduje dynamiczne identyfikatory. Nie jest to substytut migration ledger — ownership i historia zmian schematu należą do przyszłego Q-004.

Pierwszy provider `PdoDatabase`:

- wymusza exception mode,
- mapuje błędy zapytań do `QueryFailed`,
- mapuje błędy połączenia do `ProviderUnavailable`,
- zachowuje wyjątek aplikacyjny po poprawnym rollbacku,
- odrzuca nested transactions zamiast udawać przenośne savepointy,
- nie ujawnia PDO w publicznym contract.

SQLite in-memory jest providerem testowym contract suite; CI jawnie włącza `pdo_sqlite`. Sam runtime wymaga `ext-pdo`, natomiast wybór i konfiguracja produkcyjnego drivera pozostaje instalacyjną decyzją composition root.

`PdoDatabaseFactory` jest zarejestrowane wewnętrznie w Core, ale **database capability nie jest jeszcze publikowane globalnie**, ponieważ nie istnieje jeszcze kanoniczny installation storage config/connection. Publikowanie niekonfigurowanej bazy jako capability byłoby fałszywą gwarancją dostępności.

Następnym krokiem storage jest Q-004: migration engine z owner/package ledger, planem, dry-run metadata, reversible/destructive flags i preflight hooks.
