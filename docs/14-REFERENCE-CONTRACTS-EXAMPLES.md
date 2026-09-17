# 14 — Reference Contracts and Examples

Ten dokument zawiera pseudokod. Nazwy klas nie są jeszcze zamrożonym API; pokazują oczekiwany podział odpowiedzialności.

## 1. Module manifest

```json
{
  "$schema": "https://example.invalid/miniportal/module.schema.json",
  "schema": 1,
  "id": "minecraft-files",
  "name": "Minecraft Files",
  "version": "1.0.0",
  "type": "module",
  "requires": {
    "core": "^1.0",
    "uiApi": "^1.0",
    "capabilities": {
      "filesystem": "^1.0"
    }
  },
  "optional": {
    "capabilities": {
      "archive": "^1.0"
    }
  },
  "entrypoint": "SyntaxDevTeam\\MiniPortal\\MinecraftFiles\\Module"
}
```

## 2. Theme manifest

```json
{
  "schema": 1,
  "id": "syntax-dark",
  "name": "Syntax Dark",
  "version": "1.0.0",
  "type": "theme",
  "requires": {
    "core": "^1.0",
    "uiApi": "^1.0"
  },
  "extends": "base",
  "layouts": ["application", "auth", "fullscreen"]
}
```

## 3. Capability provider manifest fragment

```json
{
  "id": "node-agent-filesystem",
  "type": "provider",
  "provides": {
    "filesystem": "1.2.0"
  },
  "requires": {
    "core": "^1.0"
  }
}
```

## 4. Module interface

```php
interface Module
{
    public function register(ModuleRegistration $registration): void;

    public function boot(ModuleContext $context): void;

    public function health(): ModuleHealth;
}
```

`register()` deklaruje dependencies z runtime registry, routes, permissions, migrations i UI contributions. Nie wykonuje ciężkich remote calls.

## 5. Filesystem contract

```php
interface Filesystem
{
    public function scope(FilesystemRoot $root, FilePolicy $policy): ScopedFilesystem;
}

interface ScopedFilesystem
{
    public function list(Path $path): FileCollection;
    public function stat(Path $path): FileMetadata;
    public function read(Path $path): FileContents;
    public function write(Path $path, FileContents $contents): void;
    public function copy(Path $source, Path $target): void;
    public function move(Path $source, Path $target): void;
    public function delete(Path $path): void;
}
```

Realne API prawdopodobnie rozdzieli streaming i operations bardziej precyzyjnie.

## 6. Minecraft FileScope

```php
$scope = $filesystem->scope(
    FilesystemRoot::forResource('minecraft-server', $server->id()),
    FilePolicy::builder()
        ->root($server->workingDirectory())
        ->read($permissions->can('minecraft.files.read'))
        ->write($permissions->can('minecraft.files.write'))
        ->delete($permissions->can('minecraft.files.delete'))
        ->denyEscape()
        ->build()
);
```

Module określa resource i politykę. Provider wykonuje operację.

## 7. PageDefinition

```php
return Page::make('minecraft.files')
    ->title($server->name() . ' — Pliki')
    ->layout(LayoutRole::APPLICATION)
    ->breadcrumbs([
        Breadcrumb::link('Serwery', route('servers.index')),
        Breadcrumb::link($server->name(), route('servers.show', $server->id())),
        Breadcrumb::current('Pliki'),
    ])
    ->content(
        FileBrowser::make($scope)
            ->searchable(true)
            ->upload($permissions->can('minecraft.files.upload'))
    );
```

## 8. Server-side table

```php
return Table::make('users')
    ->column(TextColumn::make('name')->sortable())
    ->column(BadgeColumn::make('status'))
    ->source(TableSource::route('users.table'))
    ->search(Search::serverSide(delayMs: 250))
    ->pagination(Pagination::cursorOrPage());
```

Module nie generuje `hx-get` ani ręcznie JS debounce.

## 9. Live progress

```php
Progress::make('backup-progress')
    ->label('Backup')
    ->live(JobReference::from($jobId));
```

Realtime adapter może użyć SSE do aktualizacji fragmentu.

## 10. UI renderer resolution

Pseudokod:

```php
function rendererFor(Component $component, Theme $theme): Renderer
{
    return $theme->overrideFor($component::type())
        ?? $theme->parentOverrideFor($component::type())
        ?? $baseTheme->rendererFor($component::type());
}
```

Brak ostatniego renderera oznacza błąd platformy i powinien zostać wykryty przez UI contract tests przed release.

## 11. Module dispatcher

```php
try {
    $module = $registry->active($route->moduleId());
    $breaker->assertAvailable($module);
    return $moduleRunner->handle($module, $request);
} catch (ModuleThrowable $e) {
    $errorId = $errors->record($e, $requestContext);
    $breaker->recordFailure($module, $e);
    return $ui->moduleUnavailable($module, $errorId);
}
```

Realny boundary musi uwzględniać zwykłe `Throwable` i poprawną klasyfikację, nie tylko sztuczny typ z przykładu.

## 12. Preflight result

```json
{
  "package": "minecraft-files",
  "version": "1.8.0",
  "status": "ready",
  "checks": [
    {"id": "integrity", "status": "pass"},
    {"id": "manifest", "status": "pass"},
    {"id": "dependencies", "status": "pass"},
    {"id": "php-syntax", "status": "pass"},
    {"id": "autoload", "status": "pass"},
    {"id": "registration", "status": "pass"},
    {"id": "migrations", "status": "pass"}
  ]
}
```

UI prezentuje dane preflight, nie parsuje tekstu CLI.

## 13. Package state

```text
DISCOVERED
VALIDATED
STAGED
READY
ACTIVE
DEGRADED
DISABLED
FAILED_PREFLIGHT
INCOMPATIBLE
MIGRATION_BLOCKED
```

Stan powinien być modelowany jawnie, nie jako zestaw przypadkowych booleanów.

## 14. Error response model

```php
final readonly class ErrorReference
{
    public function __construct(
        public string $id,
        public ErrorCategory $category,
        public SafeMessage $message,
    ) {}
}
```

Stack trace i technical context pozostają w server logs.

## 15. Przykład theme fallback

Theme `glass` nadpisuje:

```text
Button
Card
Navigation
ApplicationLayout
```

Nie nadpisuje `DatePicker`.

Renderer wybiera `BaseTheme/DatePicker`; moduł działa bez zmian. To jest wymagane zachowanie platformy, nie przypadek awaryjny.