# Configuration reference

Override only the options you need in your application's parameters:

```php
return [
    'yii3/debug' => [
        'allowedIPs' => ['127.0.0.1', '::1'],
        'historySize' => 50,
        'routePrefix' => '/debug',
        'storage' => [
            'path' => '@runtime/debug',
        ],
        'toolbar' => [
            'position' => 'bottom',
        ],
    ],
];
```

These are the defaults: local access only, up to 50 retained requests, and captures stored under `@runtime/debug`.
Changing `routePrefix` also changes the History URL, which the toolbar middleware serves directly. The storage path
accepts a registered Yii alias.

The `application` block is optional. Leave it out and the Configuration panel reads the name and version from the
Composer root package, the environment from `APP_ENV`, and the debug mode from `APP_DEBUG`; every key you declare
wins over the runtime value.

## Registering collectors and panels

The Inertia provider registers itself: once `php-forge/inertia` is installed, the packaged parameters declare its
collector and panel under the `inertia` ID, the packaged `events-web` group routes every protocol result to that
collector, and the packaged `di-web` group builds it with the host redaction policy. Declare every other collector and
panel your application wants under `yii3/debug.collectors` and `yii3/debug.panels`, keyed by the stable ID the class
reports from `id()`:

```php
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

return [
    'yii3/debug' => [
        'collectors' => [
            'vite' => ViteCollector::class,
            'cache' => ['class' => Acme\Debug\CacheCollector::class, 'enabled' => true],
        ],
        'panels' => [
            'vite' => ['class' => VitePanel::class, 'title' => 'Vite assets', 'icon' => 'asset', 'position' => 1],
            'cache' => ['class' => Acme\Debug\CachePanel::class, 'title' => 'Cache operations', 'icon' => 'db'],
        ],
    ],
];
```

An application that wants no Inertia capture disables both packaged entries:

```php
use PHPForge\Inertia\Debug\{InertiaCollector, InertiaPanel};

return [
    'yii3/debug' => [
        'collectors' => ['inertia' => ['class' => InertiaCollector::class, 'enabled' => false]],
        'panels' => ['inertia' => ['class' => InertiaPanel::class, 'enabled' => false]],
    ],
];
```

- The array key is the stable ID and must equal what the class returns from `id()`; a mismatch is rejected by name.
  Renaming a panel never changes that key, so stored captures and panel URLs stay stable.
- A collector entry accepts `class` and `enabled`. A panel entry accepts `class`, `title`, `icon`, `enabled`, and
  `position`. Any other key is rejected by name. A plain class string is the short form of `['class' => ...]`.
- `enabled: false` removes the entry without referencing its class, so an optional package that is not installed is
  not an error. Disabling a provider completely means disabling both its collector and its panel entry.
- `title` and `icon` override the provider defaults. Only panels built on `PHPForge\Debug\Panel` carry an override;
  a panel rendering its own presentation is rejected explicitly.
- `icon` is a Debug Core icon key matching `[a-z0-9][a-z0-9-]*`. An unknown key renders no icon.
- `position` orders the `Extensions` group ascending; entries without one follow, sorted by effective title and then
  by ID. Built-in panels keep their fixed order (History, Request, Logs, Events, Profiling, Database, Assets) and
  reject `position`.

An extension appears in the sidebar only when the selected request contains its data. Vite's **Production** label
means it is inspecting built assets in your development application, not that the debugger can run in production.

### Provider event listeners

The debugger packages the Inertia listener only. Declare the listener of any other provider in your application's
`events-web` group, so the instance that captures the event is the same container instance the debugger reads:

```php
use PHPForge\Vite\Debug\ViteCollector;
use PHPForge\Vite\Event\AssetsResolved;

return [
    AssetsResolved::class => [ViteCollector::class],
];
```

The container injects its `Psr\EventDispatcher\EventDispatcherInterface` into `PHPForge\Vite\Vite` and
`PHPForge\Inertia\Protocol`, so no further wiring is needed. Keep the debugger, Debug Core, and the provider
packages up to date together in the application's lock file.

### Redacting a provider capture

`PHPForge\Debug\Capture\CapturePolicy` holds the redaction rules the Request panel applies. The packaged Inertia
collector already receives them. Hand the same rules to an application-owned collector that captures user data, in the
application's `di-web` group:

```php
use Acme\Debug\CacheCollector;
use PHPForge\Debug\Capture\CapturePolicy;

return [
    CacheCollector::class => static fn(CapturePolicy $capturePolicy): CacheCollector => new CacheCollector(
        $capturePolicy->redact(...),
    ),
];
```

## Upgrading from the extensions flags

The `extensions` block is gone, and `config/di-web.php` reads `collectors` and `panels` alone. The key is ignored, so
an application that still sets `yii3/debug.extensions` silently loses the captures of its application-owned
extensions: no collector, panel, or listener is wired for them. The packaged Inertia provider is not affected, because
it stays wired whenever `php-forge/inertia` is installed.

Before:

```php
return [
    'yii3/debug' => [
        'extensions' => [
            'inertia' => true,
            'vite' => true,
        ],
    ],
];
```

After, in the application parameters, keyed by the stable ID each class reports from `id()`; the `inertia` flag needs
no replacement:

```php
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};

return [
    'yii3/debug' => [
        'collectors' => [
            'vite' => ViteCollector::class,
        ],
        'panels' => [
            'vite' => VitePanel::class,
        ],
    ],
];
```

Each flag also attached the collector as a PSR-14 listener. The debugger packages the Inertia listener only, so
declare the Vite one in the application's `events-web` group:

```php
use PHPForge\Vite\Debug\ViteCollector;
use PHPForge\Vite\Event\AssetsResolved;

return [
    AssetsResolved::class => [ViteCollector::class],
];
```

### Upgrading a custom panel

`SummaryAwarePanelInterface`, `ContextAwarePanelInterface`, and `ContextAndSummaryAwarePanelInterface` are gone, with
their `renderWithSummary()`, `renderWithContext()`, and `renderWithContextAndSummary()` methods. A panel implements
`Yii3\Debug\Panel\ExtensionPanelInterface` alone, and its single `render()` receives a
`Yii3\Debug\Panel\PanelRenderInput` exposing `$input->payload`, `$input->context`, and `$input->summary`.

Before:

```php
use PHPForge\Debug\Panel\PanelRenderContext;
use Yii3\Debug\Panel\ContextAwarePanelInterface;

final class CachePanel implements ContextAwarePanelInterface
{
    public function render(array $payload): string
    {
        // renders without the debugger request context
    }

    public function renderWithContext(array $payload, PanelRenderContext $context): string
    {
        // renders with the debugger request context
    }
}
```

After:

```php
use Yii3\Debug\Panel\{ExtensionPanelInterface, PanelRenderInput};

final class CachePanel implements ExtensionPanelInterface
{
    public function render(PanelRenderInput $input): string
    {
        // reads $input->payload, and $input->context or $input->summary when it needs them
    }
}
```

`Yii3\Debug\Web\PageWindow::single()` is gone; `PageWindow::paginate($rows, null, null)` keeps every row on one page.
`php-forge/vite` moved from `require` to `require-dev`, so an application registering the Vite panel installs the
package itself.

## Custom collectors and panels

A collector implements `PHPForge\Debug\CollectorInterface` — the single contract shared by the built-in collectors,
the provider packages, and application-owned code — and returns the payload to persist from `capture()`. A panel
extends `PHPForge\Debug\Panel` and turns that payload into a presentation. Register both under the same ID:

```php
return [
    'yii3/debug' => [
        'collectors' => ['cache' => Acme\Debug\CacheCollector::class],
        'panels' => ['cache' => ['class' => Acme\Debug\CachePanel::class, 'title' => 'Cache operations']],
    ],
];
```

`Yii3\Debug\ExtensionRegistry` also accepts instances at runtime:

```php
$registry = $registry
    ->withCollector(new Acme\Debug\CacheCollector())
    ->withPanel(new Acme\Debug\CachePanel());
```

Collectors declare their own ID; the registry rejects an empty or duplicate one, and reports the IDs the
configuration removed through `disabled()`, which a host tells apart from an ID it never knew.

## Capture lifecycle

The toolbar middleware runs the request with the collectors active, but it does not write the snapshot when the
pipeline returns. Instead it hands a finalizer to `Yii3\Debug\Capture\DeferredCapture`, and the capture is written
when the application dispatches `Yiisoft\Yii\Http\Event\ApplicationShutdown`. That event is the last step of the
Yii HTTP runner, after the response is emitted and after the `AfterEmit` listeners of `yiisoft/log` and
`yiisoft/profiler` have flushed.

Deferral is what keeps three kinds of work in the snapshot:

- Queries issued while `Yiisoft\DataResponse\DataResponse` renders the view lazily, which happens only when the body
  is read.
- Log messages and profiler spans flushed at `AfterEmit`. The debugger registers its own profiler target, so spans the
  profiler moved out of memory on flush still reach the Profiling and Logs panels.
- Events dispatched after the middleware returned, such as `AfterRequest` and `AfterEmit`.

This requires the application to merge the package's `events-web` configuration group, which the Yii HTTP runner
loads. Rebuild the merged configuration after installing or updating the package:

```shell
composer yii-config-rebuild
```

Then confirm that the application's merge plan (`config/.merge-plan.php` unless `config-plugin-options.merge-plan-file`
says otherwise) lists `yii3/debug/config/events-web.php` under `events-web`.

If the application never dispatches that event — a console command, a custom runner, or a fatal error during emission
— a PHP shutdown function registered on the first deferral writes the capture instead, so a request is never lost.
Without a collector coordinator there is nothing to defer, and the fallback summary is written inside the pipeline as
before.

## Automatic wiring

The debugger attaches itself to the services the application already declares, so no development-only DI override is
needed.

- `Psr\EventDispatcher\EventDispatcherInterface` is decorated by `Yii3\Debug\DebugServiceProvider`, published in the
  `di-providers` and `di-providers-web` groups. The application definition is kept; the provider only wraps what it
  resolves to.
- `Yiisoft\Assets\AssetLoaderInterface` is decorated by the same provider, so every bundle the application loads reaches
  the Asset Bundles panel. The definition `yiisoft/assets` publishes in the `di` group is kept; the provider only wraps
  what it resolves to.
- `Yiisoft\Db\Connection\ConnectionInterface` receives the application logger and the debugger profiler from the
  `bootstrap` group. A connection no PDO driver backs, or an application without a connection, is left untouched.

Both groups must belong to the provider and bootstrap groups the application runner loads, and the decorations are
idempotent, so an application wiring the connection itself keeps working.

Database capture still requires a Yii DB 2 driver. EXPLAIN supports MySQL, SQLite, and PostgreSQL and requires the
application's `Yiisoft\Db\Connection\ConnectionInterface` binding to match the captured queries. Leave EXPLAIN
unconfigured when one binding cannot represent all captured connections.

## Logger targets

`Psr\Log\LoggerInterface` is not decorated. The debugger merges its `debug` target, and a `stream` target, into
`yiisoft/log.targets`, so the application must build its logger from `$params['yiisoft/log']['targets']`. The
`yiisoft/app` template already builds the logger this way:

```php
use Psr\Log\LoggerInterface;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\Log\{Logger, StreamTarget};

/** @var array $params */

return [
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from(
                array_values($params['yiisoft/log']['targets'] ?? [StreamTarget::class]),
            ),
        ],
    ],
];
```

An application that hardcodes its target list keeps working, but its Logs panel stays empty. A template that relies on
the `?? [StreamTarget::class]` fallback needs no parameters of its own: the merged key bypasses that fallback, yet it
already carries a `stream` target beside the debugger's. Declare targets under the same key to add your own, or to
replace a merged one under the same name:

```php
use Yiisoft\Log\Target\File\FileTarget;

return [
    'yiisoft/log' => [
        'targets' => [
            'file' => FileTarget::class,
        ],
    ],
];
```

## IDE links

Source traces use `ide://` links by default. Set `traceLine` to `false` for plain file names and line numbers, or
provide your editor's URL template in the `yii3/debug` parameters:

```php
'traceLine' => '<a href="phpstorm://open?file={file}&line={line}">{text}</a>',
```

For containers or remote environments, map captured paths to your local project:

```php
'tracePathMappings' => ['/var/www/html' => '/home/developer/projects/app'],
```

---

[← Back to documentation](../README.md#documentation)
