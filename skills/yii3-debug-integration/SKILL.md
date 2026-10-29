---
name: yii3-debug-integration
description: Install, configure, or troubleshoot yii3/debug in a Yii 3 application, including Yii Config Plugin groups, DI providers, logger targets, middleware, storage, collectors, and request-level verification. Do not use for Yii 2 debug modules.
license: BSD-3-Clause
metadata:
  source: https://github.com/yii3/debug
---

# Integrate Yii3 Debug

Match the installed `yii3/debug` files before configuring the application. Installing this skill does not install the
Composer package, enable the debugger, or prove the container and request pipeline work.

## Establish the revision and application shape

For the root package, inspect `composer show --self --format=json`, `HEAD`, and the working tree. For an installed
dependency, resolve the configured vendor directory and inspect `vendor/composer/installed.php` for `yii3/debug`:
`install_path`, `pretty_version`, and `reference`. Compare `composer.lock`. A symlinked path may contain a different
`HEAD` or uncommitted files than both metadata sources; let the actual installed files govern and report the mismatch.

Inspect every HTTP entry script, runner, aggregate configuration group, logger definition, and middleware pipeline.
Applications with Site and API runners may integrate only one of them. Identify that choice before editing.

In a repository checkout, start with `docs/index.md`, then read only the relevant `docs/examples.md`,
`docs/configuration.md`, or `docs/testing.md`. Composer distributions omit checkout-only `docs/`, and archive rules
reserve any future `examples/` directory for non-distributed material. Use the matching repository revision when
available, otherwise inspect installed `composer.json`, `config/`, source PHPDoc, and the changelog. This skill contains
every essential integration step and does not require excluded files. Do not substitute `yii2-extensions/debug`,
`yiisoft/yii2-debug`, or their module APIs.

## Install for development only

The current development line requires PHP 8.3 or later and Yii Config Plugin. Keep it outside production installs:

```bash
composer require --dev "yii3/debug:^0.1@dev"
composer yii-config-rebuild
```

Run this in an existing Yii 3 application. Composer must be able to resolve providers for the
`yiisoft/router-implementation` and `yiisoft/db-implementation` virtual packages. Preserve the application's selected
router and database driver. If either provider is absent, identify the application's intended backend and ask for that
choice when it is not already evident; do not install an arbitrary driver merely to make dependency resolution pass.

Set the process environment `APP_ENV` to `dev`, `debug`, or `test`. The runner's configuration `environment` argument
does not replace `APP_ENV`. Missing, unknown, and production values intentionally return empty package configuration.

## Connect all application-owned integration points

After rebuilding, inspect the application's merge plan and aggregate definitions. The package publishes:

- `params`: debugger options, aliases, `yiisoft/log.targets`, profiler targets, and debugger middleware IDs;
- `di`: collectors, panels, storage, toolbar, trace, and database definitions;
- `di-web`: the extension registry built from application parameters;
- `di-providers` and `di-providers-web`: the idempotent `DebugServiceProvider`;
- `bootstrap`: optional database connection instrumentation;
- `events-web`: deferred capture finalization and optional mail collection.

Ensure each intended runner loads aggregates containing `$params`, `$di` plus `$di-web`, one provider group,
`$bootstrap`, and `$events-web`. Check the exact group names passed to the runner. Presence in `.merge-plan.php` proves
package discovery, not runner consumption or container resolution.

Standard Yii runners apply `Yiisoft\Config\Modifier\ReverseMerge` to the selected event groups and then
`Yiisoft\Config\Modifier\RecursiveMerge` to the selected parameter and event groups, including their nested groups.
If a custom bootstrap constructs `Yiisoft\Config\Config` directly, apply both modifiers in that order to the same
group names; otherwise duplicate nested keys can fail, replace merged targets and listeners, or change listener order.

The application must also do both of the following:

- Build `Psr\Log\LoggerInterface` from `array_values($params['yiisoft/log']['targets'] ?? [StreamTarget::class])`,
  normally with `Yiisoft\Definitions\ReferencesArray`. The `Yiisoft\Log\StreamTarget` fallback keeps the logger
  resolvable when the package returns empty configuration. A hardcoded target list omits `DebugLogTarget`, so Logs
  stays empty.
- Prepend `...($params['yiisoft/middleware-dispatcher']['middlewares'] ?? [])` to every intended middleware list.
  `DebugRouteMiddleware` serves the configured prefix directly and `RequestCaptureMiddleware` captures requests and
  injects the HTML toolbar. No application route is needed.

Confirm that the configured `routePrefix` does not overlap an application route or another debugger. The debug route
middleware claims that prefix before the application router; change it when the application already owns the path.

Preserve the application's event dispatcher, asset loader, database, logger, routes, and middleware. The service
provider decorates `Psr\EventDispatcher\EventDispatcherInterface` and `Yiisoft\Assets\AssetLoaderInterface`; it does
not define them. Load providers before first resolution. The bootstrap callback instruments an existing PDO-backed
`Yiisoft\Db\Connection\ConnectionInterface` with the application logger and debugger profiler; an application without
a database remains valid.

The default snapshot and mail paths are `@runtime/debug` and `@runtime/debug/mail`. Verify that `@runtime` and `@vendor`
resolve and that storage is writable outside the public Web root. Keep the loopback-only IP allowlist unless the user
identifies another trusted development address. Never enable the debugger in production.

## Keep optional integrations in their owners

The adapter owns the built-in collectors and framework wiring. `php-forge/debug` owns portable collector and panel
contracts; `php-forge/debug-core` owns persistence and the compiled UI. Optional application packages own their events
and services.

`yii3/inertia` publishes its collector, panel, listener, and policy-aware definition through its own groups. Vite has no
Yii 3 adapter package, so the application registers `ViteCollector` and `VitePanel` under the stable `vite` ID and
routes `AssetsResolved` to the same container collector. For application collectors, register collector and panel under
the ID returned by `id()`, add their event listener, and inject `CapturePolicy` redaction when they retain user data.

Mail needs `yiisoft/mailer`; the Symfony adapter adds full RFC 5322 storage. User capture needs `yiisoft/user`; RBAC
roles need an application `Yiisoft\Rbac\ManagerInterface`. Their absence must leave the base debugger resolvable.

## Verify observable integration

Keep these outcomes separate:

- **Package resolution:** record the actual installed path, version/reference, dependency versions, and environment.
- **Configuration discovery:** rebuild and confirm every required package file appears in the merge plan.
- **Container resolution:** resolve the logger, dispatcher, asset loader, snapshot storage, extension registry, and both
  middleware classes. Confirm the dispatcher and loader are decorated once and the logger owns the debug target.
- **Functional request:** make an allowed HTML request, confirm toolbar injection, find the request in History, open
  it, and find a known non-sensitive entry in its Logs panel. JSON can be captured without toolbar injection.
- **Lifecycle and access:** confirm shutdown finalization, an unlisted client denial, and empty production integration.
- **Optional provider behavior:** trigger the real component event and inspect its captured payload; panel presence is
  insufficient.
- **Skill behavior:** report synchronization, client discovery, native activation, and task acceptance independently.

Static configuration, a successful Composer install, or a passing package unit suite does not prove an application
runner consumed the groups. If a browser, supported PDO driver, optional package, or native client session is missing,
report that exact limit instead of inferring success.
