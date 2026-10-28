# Getting started

Yii3 Debug is a development-only adapter around the framework-neutral `php-forge/debug` contracts and
`php-forge/debug-core` storage and UI. The package publishes Yii Config Plugin groups, but the application still owns
the aggregate groups loaded by each runner, the logger definition, and each middleware pipeline.

## Requirements

- PHP 8.3 or later.
- Composer and `yiisoft/config` 1.6 or later.
- A Yii 3 HTTP application using `yiisoft/di` and an HTTP runner that loads Yii Config Plugin groups.
- Application-selected providers for the `yiisoft/router-implementation` and `yiisoft/db-implementation` virtual
  packages.
- A writable runtime directory outside the public Web root.

The current package line is a development release. Install it only for development:

```bash
composer require --dev "yii3/debug:^0.1@dev"
composer yii-config-rebuild
```

Keep the application's existing router and database driver. If Composer reports a missing virtual implementation,
choose a provider that matches the application, such as `yiisoft/router-fastroute` for FastRoute or the appropriate
Yii DB driver. Do not add SQLite or another driver merely to silence dependency resolution.

Run the application with `APP_ENV=dev`, `APP_ENV=debug`, or `APP_ENV=test`. The runner's own `environment` argument does
not set `APP_ENV`. Unknown, empty, and production values make every package configuration group return an empty array.

## Load every integration group

The package declares these groups in `composer.json`:

| Package group                       | Package responsibility                                  | Application responsibility                               |
| ----------------------------------- | ------------------------------------------------------- | -------------------------------------------------------- |
| `params`                            | Options, aliases, log targets, middleware IDs, profiler | Include `$params` in the runner's parameter group        |
| `di`                                | Collectors, storage, panels, toolbar, database services | Include `$di` in the runner's definition group           |
| `di-web`                            | Application collector and panel registry                | Include `$di-web` in each intended HTTP definition group |
| `di-providers` / `di-providers-web` | Dispatcher and asset-loader decorators                  | Load the matching provider aggregate before resolution   |
| `bootstrap`                         | Optional Yii DB connection instrumentation              | Include `$bootstrap` in the runner's bootstrap group     |
| `events-web`                        | Deferred finalization and optional mail capture         | Include `$events-web` in the runner's Web event group    |

For an application with Site and API runners, compose each aggregate explicitly. The
[application integration examples](examples.md#compose-the-runner-groups) show complete Site and API group definitions.

Pass those exact aggregate names to the corresponding `HttpApplicationRunner`. Loading the package's provider from
both provider groups is safe because its decorations are idempotent, but one provider aggregate per runner is enough.

After rebuilding configuration, inspect the application's merge plan. It should list `yii3/debug` under `params`,
`di`, `di-web`, one loaded provider group, `bootstrap`, and `events-web`. A merge-plan entry proves discovery only; it
does not prove that the application runner consumes the aggregate.

The standard Yii HTTP runner recursively merges its selected parameter and event groups, including the nested groups.
If a custom bootstrap constructs `Yiisoft\Config\Config` directly, apply `Yiisoft\Config\Modifier\RecursiveMerge` to
the same group names so nested targets and listeners merge instead of colliding or replacing one another.

## Build the logger from merged targets

The debugger does not replace `Psr\Log\LoggerInterface`. It contributes `debug` and `stream` entries under
`yiisoft/log.targets`. The application logger must resolve that merged list as shown in the
[logger example](examples.md#configure-application-metadata-and-log-targets).

Add application targets under the same parameter key. A matching named key replaces that target; a new key adds one.
A hardcoded constructor list keeps application logging alive but leaves the debugger's Logs panel empty.

## Include both debugger middlewares

The package contributes `DebugRouteMiddleware` followed by `RequestCaptureMiddleware` under
`yiisoft/middleware-dispatcher.middlewares`. Spread the merged list before the application middleware in every pipeline
that should expose `/debug` and capture requests. See the
[middleware pipeline example](examples.md#extend-each-middleware-pipeline).

`DebugRouteMiddleware` serves the debugger directly; no application route is required. A second HTTP pipeline that
does not spread the merged list still loads the package definitions, providers, bootstrap callbacks, and listeners but
does not serve Debug pages or capture requests. Treat that as an intentional per-pipeline choice, not a working global
integration.

## Keep the application's services

`DebugServiceProvider` decorates the application's `Psr\EventDispatcher\EventDispatcherInterface` and
`Yiisoft\Assets\AssetLoaderInterface` definitions. It does not own either service. Register the application services
normally and load the provider group before either one is first resolved.

The bootstrap callback instruments an existing `Yiisoft\Db\Connection\ConnectionInterface` only when it uses a PDO
driver and the application also resolves its logger and the debugger profiler. Applications without a database remain
valid. `events-web` finalizes the capture on `ApplicationShutdown`; a shutdown fallback handles runners that never
dispatch that event, but normal Yii HTTP applications should keep the listener group so late logs, spans, and lazy
queries reach the snapshot.

The default storage is `@runtime/debug`, with captured mail under `@runtime/debug/mail`. Ensure the application's alias
configuration defines writable `@runtime` and `@vendor` paths. Keep both outside the public Web root.

## Verify a request, not only configuration

- Confirm the installed package path, version, and selected reference with `composer show yii3/debug`,
  `vendor/composer/installed.php`, and `composer.lock`. Inspect a symlinked checkout's current `HEAD` and files too.
- Rebuild and inspect the merge plan, then confirm each runner names aggregates that contain the package groups.
- Resolve the logger, dispatcher, asset loader, storage, registry, and both middleware classes from the application
  container. The dispatcher and asset loader should be decorated, not replaced at the definition site.
- Make an allowed HTML request and verify that the response contains the toolbar. Open `/debug`, find the request in
  History, open it, and confirm its Logs panel contains a known non-sensitive entry.
- Verify that an unlisted client receives `403 Forbidden` for a Debug URL and that a production process has no debugger
  definitions or middleware.

## Next steps

- ⚙️ [Configuration reference](configuration.md)
- 💡 [Application integration examples](examples.md)
- 🖼️ [Panel screenshots](screenshots.md)
- 🤖 [AI assistance](agents.md)
- 🧪 [Testing guide](testing.md)

[Back to documentation](../README.md#documentation)
