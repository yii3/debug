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
            'mailPath' => '@runtime/debug/mail',
        ],
        'toolbar' => [
            'position' => 'bottom',
        ],
    ],
];
```

These are the defaults: local access only, up to 50 retained requests, captures stored under `@runtime/debug`, and the
`.eml` files of captured mail under `@runtime/debug/mail`. Changing `routePrefix` also changes the History URL, which
`Yii3\Debug\Middleware\DebugRouteMiddleware` serves directly. Both storage paths accept a registered Yii alias and
share `storage.dirMode` and `storage.fileMode`.

`Yii3\Debug\Middleware\ToolbarOptions::fromParams()` reads `routePrefix`, `historySize`,
`database.excessiveCallerThreshold`, and the `toolbar` block once, and the container hands the same instance to both
middlewares, the toolbar payload, and the debugger pages. A key you declare with the wrong type fails at startup with
an `InvalidArgumentException` naming the key.

The package registers two middlewares in `yiisoft/middleware-dispatcher.middlewares`, in this order:

- `Yii3\Debug\Middleware\DebugRouteMiddleware` serves the debugger pages under `routePrefix` and answers
  `403 Forbidden` to a client outside `allowedIPs`.
- `Yii3\Debug\Middleware\RequestCaptureMiddleware` captures every other request from an allowed client and injects
  the toolbar into HTML responses. It passes the debugger pages through uncaptured, so an application that serves
  them itself can register it alone.

Both write a capture still pending from an earlier request before doing anything else, whichever of the two runs
first.

The `application` block is optional. Leave it out and the Configuration panel reads the name and version from the
Composer root package, the environment from `APP_ENV`, and the debug mode from `APP_DEBUG`; every key you declare
wins over the runtime value.

## Registering collectors and panels

The debugger ships no collector or panel beyond its built-ins and names no provider package. Declare every collector
and panel your application wants under `yii3/debug.collectors` and `yii3/debug.panels`, keyed by the stable ID the
class reports from `id()`:

```php
return [
    'yii3/debug' => [
        'collectors' => [
            'cache' => ['class' => Acme\Debug\CacheCollector::class, 'enabled' => true],
        ],
        'panels' => [
            'cache' => ['class' => Acme\Debug\CachePanel::class, 'title' => 'Cache operations', 'icon' => 'db'],
        ],
    ],
];
```

An entry another package's configuration declares, such as the `inertia` entries `yii3/inertia` ships, is switched
off from the application params; disable both the collector and the panel:

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
  by ID. Built-in panels keep their fixed order (History, Request, Logs, Events, Profiling, Database, Mail, User,
  Dump, Assets) and reject `position`.

An extension appears in the sidebar only when the selected request contains its data. Vite's **Production** label
means it is inspecting built assets in your development application, not that the debugger can run in production.

### Provider event listeners

Declare the listener of every provider collector in an `events-web` group, so the instance that captures the event is
the same container instance the debugger reads:

```php
use Acme\Cache\Event\CacheAccessed;
use Acme\Debug\CacheCollector;

return [
    CacheAccessed::class => [CacheCollector::class],
];
```

The container injects its `Psr\EventDispatcher\EventDispatcherInterface` into `PHPForge\Vite\Vite` and
`PHPForge\Inertia\Protocol`, so no further wiring is needed. Keep the debugger, Debug Core, and the provider
packages up to date together in the application's lock file.

### Provider packages

Vite and Inertia register through the same three groups as the `cache` example: `params` declares the collector and
the panel, `events-web` routes the provider event to the collector, and `di-web` builds a collector that needs the host
redaction policy.

`yii3/inertia` ships the Inertia entries in its own configuration groups, so an application using it adds nothing:

```php
// yii3/inertia config/params.php
'yii3/debug' => [
    'collectors' => ['inertia' => InertiaCollector::class],
    'panels' => ['inertia' => InertiaPanel::class],
],

// yii3/inertia config/events-web.php
ProtocolResultCreated::class => [InertiaCollector::class],

// yii3/inertia config/di-web.php, only when Debug Core is installed
InertiaCollector::class => static fn(CapturePolicy $policy): InertiaCollector
    => new InertiaCollector($policy->redact(...), $policy->redactUrl(...)),
```

Vite has no Yii3 adapter package, so the application declares it; `ViteCollector` needs no container definition:

```php
use PHPForge\Vite\Debug\{ViteCollector, VitePanel};
use PHPForge\Vite\Event\AssetsResolved;

// config/params.php
'yii3/debug' => [
    'collectors' => ['vite' => ViteCollector::class],
    'panels' => ['vite' => VitePanel::class],
],

// config/events-web.php
AssetsResolved::class => [ViteCollector::class],
```

Without the debugger installed the `yii3/debug` params are inert and the listener only buffers: a collector records
nothing until the debugger starts it.

### Redacting a provider capture

`PHPForge\Debug\Capture\CapturePolicy` holds the redaction rules the Request panel applies. The Inertia collector
`yii3/inertia` declares already receives them. Hand the same rules to an application-owned collector that captures
user data, in the application's `di-web` group:

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
extensions: no collector, panel, or listener is wired for them.

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

After: delete the block. The `inertia` flag needs no replacement, because `yii3/inertia` declares the Inertia collector,
panel, and listener in its own configuration groups. Replace the `vite` flag with the Vite entries shown under
[Provider packages](#provider-packages). Declare application-owned extensions under `collectors` and `panels`, keyed by
the stable ID each class reports from `id()`, and their listeners in the application's `events-web` group.

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

`RequestCaptureMiddleware` runs the request with the collectors active, but it does not write the snapshot when the
pipeline returns. Instead it hands a finalizer to `Yii3\Debug\Capture\DeferredCapture`, and the capture is written
when the application dispatches `Yiisoft\Yii\Http\Event\ApplicationShutdown`. That event is the last step of the
Yii HTTP runner, after the response is emitted and after the `AfterEmit` listeners of `yiisoft/log` and
`yiisoft/profiler` have flushed.

Deferral is what keeps three kinds of work in the snapshot:

- Queries issued while `Yiisoft\DataResponse\DataResponse` renders the view lazily, which happens only when the body
  is read.
- Log messages and profiler spans flushed at `AfterEmit`. The debugger registers its own profiler target, so spans the
  profiler moved out of memory on flush still reach the Profiling and Logs panels.
- Events dispatched after `RequestCaptureMiddleware` returned, such as `AfterRequest` and `AfterEmit`.

This requires the application to merge the package's `events-web` configuration group, which the Yii HTTP runner
loads. Rebuild the merged configuration after installing or updating the package:

```shell
composer yii-config-rebuild
```

Then confirm that the application's merge plan (`config/.merge-plan.php` unless `config-plugin-options.merge-plan-file`
says otherwise) lists `yii3/debug/config/events-web.php` under `events-web`.

If the application never dispatches that event — a console command, a custom runner, or a fatal error during emission
— a PHP shutdown function registered with the first capture writes it instead, so a request is never lost.

The capture is armed before the request handler runs, so a script that ends inside the handler, through `exit`,
`dd()`, or a fatal error, is still written at shutdown, as the Yii2 debugger does. It carries what was collected until
then, the matched route, and the status code PHP is about to send; the response headers are empty because the
application never returned a response. A handler that throws still drops its capture, so the application failure
stays primary.

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

- `Yiisoft\Mailer\Event\AfterSend` reaches `Yii3\Debug\Collector\MailCollector` from the `events-web` group when
  `yiisoft/mailer` is installed; without it the Mail collector is not registered and the Mail panel never appears. The
  mailer dispatches the event only when it receives the application `Psr\EventDispatcher\EventDispatcherInterface`,
  which the `yiisoft/mailer-symfony` definition gets through autowiring. With that adapter installed, the body, headers,
  and stored file come from the Symfony email it sends, as in the Yii2 debugger. Each message is stored under
  `storage.mailPath`, downloaded from `{routePrefix}/download-mail?file=<name>`, and deleted when its capture leaves the
  history.
- `Yiisoft\User\CurrentUser` is read by `Yii3\Debug\Collector\UserCollector` when `yiisoft/user` is installed; without
  it the User collector is not registered and the User panel never appears, and a container that defines no current
  user records nothing. The identity is read when the response leaves the application, before the session closes. A
  guest request shows the panel empty state and a `Guest` toolbar metric; a signed-in one shows the user ID, as in the
  Yii2 debugger. Identity keys the capture policy lists (such as `password_hash` and `auth_key`) are stored redacted,
  also inside nested arrays and objects. A failure while reading the identity never breaks the request; it is recorded
  as a User panel failure instead. When the container defines `Yiisoft\Rbac\ManagerInterface` (`yiisoft/rbac`), the
  roles and permissions assigned to the user are listed too; a manager that fails is ignored, so the identity stays
  inspectable.
- The `yiisoft/var-dumper` default handler is decorated by `Yii3\Debug\Collector\DumpCollector` while a request is
  captured, so `VarDumper::dump()` and the `d()`, `dump()`, and `dd()` helpers keep printing through the handler the
  application set, and each value also appears in the Dump panel with the file and line that dumped it. The previous
  handler is restored when the capture ends, unless the application replaced it during the request. When
  `symfony/var-dumper` is installed and loaded first (Codeception and PsySH require it), the global `dump()` and `dd()`
  are Symfony's and are not captured; `d()` and `VarDumper::dump()` still are.

Both groups must belong to the provider and bootstrap groups the application runner loads, and the decorations are
idempotent, so an application wiring the connection itself keeps working.

The User panel reads the identity attributes from `toArray()`, then `jsonSerialize()` when it returns an array, then
the public properties. An identity that wraps an entity exposes none of its own, so give the collector a reader in
`user.identityData`:

```php
use App\Identity\UserIdentity;

return [
    'yii3/debug' => [
        'user' => [
            'identityData' => static fn(UserIdentity $identity): array => [
                'id' => $identity->getId(),
                ...get_object_vars($identity->user),
            ],
        ],
    ],
];
```

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
