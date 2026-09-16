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

## Inertia and Vite

Enable either integration when your application uses the corresponding package:

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

Both are disabled by default. Each flag registers the collector and panel owned by the provider package —
`PHPForge\Inertia\Debug\InertiaCollector`/`InertiaPanel` and `PHPForge\Vite\Debug\ViteCollector`/`VitePanel` — and
attaches the collector as a PSR-14 listener for `PHPForge\Inertia\Event\ProtocolResultCreated` and
`PHPForge\Vite\Event\AssetsResolved`. The container injects its `Psr\EventDispatcher\EventDispatcherInterface` into
`PHPForge\Vite\Vite` and `PHPForge\Inertia\Protocol`, so the application needs no further wiring. Enabling a flag
without its provider package installed fails with an explicit container error. Keep the debugger, Debug Core, and
enabled integration packages up to date together in the application's lock file.

An extension appears in the sidebar only when the selected request contains its data. Vite's **Production** label
means it is inspecting built assets in your development application, not that the debugger can run in production.

## Custom collectors and panels

A collector implements `PHPForge\Debug\CollectorInterface` — the single contract shared by the built-in collectors,
the provider packages, and application-owned code — and returns the payload to persist from `capture()`. Register it
with the panel that reads it through the extension registry:

```php
$registry = $registry
    ->withCollector(new Acme\Debug\CacheCollector())
    ->withPanel(new Acme\Debug\CachePanel());
```

Collectors declare their own ID; the registry rejects an empty or duplicate one.

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
