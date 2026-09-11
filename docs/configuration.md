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
Changing `routePrefix` also changes the History URL. The storage path accepts a registered Yii alias.

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

## Database

Database capture requires a Yii DB 2 driver and an instrumented application connection. For example, if your SQLite
application already registers its configured connection as `Yiisoft\Db\Sqlite\Connection`, add this factory to your
development-only DI configuration. The container supplies that connection and the debugger's registered profiler:

```php
use Yii3\Debug\Db\DebugDbProfiler;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Sqlite\Connection;

return [
    ConnectionInterface::class => static function (
        Connection $connection,
        DebugDbProfiler $debugDbProfiler,
    ): ConnectionInterface {
        $debugDbProfiler->instrument($connection);

        return $connection;
    },
];
```

Keep the existing concrete `Connection` registration and its driver settings; it must not resolve back to
`ConnectionInterface`. For another PDO driver, use its configured concrete connection class instead of SQLite's.
EXPLAIN supports MySQL, SQLite, and PostgreSQL and
requires the application's `Yiisoft\Db\Connection\ConnectionInterface` binding to match the captured queries.
Leave EXPLAIN unconfigured when one binding cannot represent all captured connections.

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
