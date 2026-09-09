<!-- markdownlint-disable MD041 -->
<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_dark.svg">
        <source media="(prefers-color-scheme: light)" srcset="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg">
        <img src="https://www.yiiframework.com/image/design/logo/yii3_full_for_light.svg" alt="Yii Framework" width="80%">
    </picture>
    <h1 align="center">Debug</h1>
    <br>
</p>
<!-- markdownlint-enable MD041 -->

<p align="center">
    <a href="https://github.com/yii3/debug/actions/workflows/build.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/debug/build.yml?style=for-the-badge&label=PHPUnit&logo=github" alt="PHPUnit">
    </a>
    <a href="https://github.com/yii3/debug/actions/workflows/mutation.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/debug/mutation.yml?style=for-the-badge&label=Mutation&logo=github" alt="Mutation Testing">
    </a>
    <a href="https://github.com/yii3/debug/actions/workflows/static.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/debug/static.yml?style=for-the-badge&label=PHPStan&logo=github" alt="PHPStan">
    </a>
    <a href="https://github.com/yii3/debug/actions/workflows/security.yml" target="_blank">
        <img src="https://img.shields.io/github/actions/workflow/status/yii3/debug/security.yml?style=for-the-badge&label=Security&logo=github" alt="Security">
    </a>
</p>

<p align="center">
    <strong>Debugger and toolbar for Yii3 applications</strong><br>
    <em>Shared Debug Core UI, scoped CSS, light/dark mode, and opt-in Inertia/Vite panels</em>
</p>

> **Development only.** Never enable the debugger in production. Keep access restricted to trusted development IPs
> and install production dependencies with `composer install --no-dev`.

## Features

<picture>
    <source media="(min-width: 768px)" srcset="docs/svgs/features.svg">
    <img src="docs/svgs/features-mobile.svg" alt="Feature overview: request history, logs and events, profiling, database, toolbar, and Inertia/Vite extensions" style="width: 100%;">
</picture>

## Quick start

### Installation

Requires PHP 8.3 or newer and a Yii3 application using Yii Config Plugin.

```shell
composer require yii3/debug --dev
```

### Enable the debugger

Set the vendor override layer in your application's `composer.json`, keeping any existing `extra` settings:

```json
{
    "extra": {
        "config-plugin-options": {
            "vendor-override-layer": "yii3/debug"
        }
    }
}
```

Rebuild the application configuration after changing this setting:

```shell
composer yii-config-rebuild
```

Run the application with `APP_ENV=dev`. The debugger also accepts `debug` and `test`; it stays disabled when the
runtime environment is missing, unknown, or production. Setting the runner's configuration environment alone is
not enough.

The package registers its routes, toolbar middleware, logging, and event capture automatically. Your application
must use the merged `yiisoft/middleware-dispatcher.middlewares` parameters for its middleware pipeline. If it defines
its own logger, preserve the targets in `yiisoft/log.targets`; custom PSR loggers and event dispatchers need a
separate integration.

### Basic usage

Open an application page, expand the toolbar at the bottom, and select a panel chip to inspect the request.
Use the Yii chip for Configuration and the PHP chip for PHP info. Switch between light and dark themes from the
toolbar, and press `Escape` to close the drawer.

Open `/debug` to browse retained requests. Select two captures in History to compare request metrics and panel
changes, then open either capture for its details. Comparison shows structural counts without exposing panel values.

## Configuration

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

### Inertia and Vite

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

Both are disabled by default. Inertia requires a compatible `yii3/inertia ^0.1` revision providing
`Yii3\Inertia\ResolvedPageObserver`. Vite uses the application's existing `php-forge/vite` configuration and entrypoints.
Keep the debugger, Debug Core, and enabled integration packages up to date together in the application's lock file.

An extension appears in the sidebar only when the selected request contains its data. Vite's **Production** label
means it is inspecting built assets in your development application, not that the debugger can run in production.

### Database

Database capture requires a Yii DB 2 driver and an instrumented application connection. In your development-only
connection configuration, use the container-provided `Yii3\Debug\Db\DebugDbProfiler` to instrument the existing PDO
connection before running queries:

```php
$debugDbProfiler->instrument($connection);
```

Keep the application's existing driver and connection settings. EXPLAIN supports MySQL, SQLite, and PostgreSQL and
requires the application's `Yiisoft\Db\Connection\ConnectionInterface` binding to match the captured queries.
Leave EXPLAIN unconfigured when one binding cannot represent all captured connections.

### IDE links

Source traces use `ide://` links by default. Set `traceLine` to `false` for plain file names and line numbers, or
provide your editor's URL template in the `yii3/debug` parameters:

```php
'traceLine' => '<a href="phpstorm://open?file={file}&line={line}">{text}</a>',
```

For containers or remote environments, map captured paths to your local project:

```php
'tracePathMappings' => ['/var/www/html' => '/home/developer/projects/app'],
```

## Security

The toolbar and debugger routes allow `127.0.0.1` and `::1` by default. Access checks use the direct client address,
not forwarded proxy headers. Add only trusted development addresses to `allowedIPs`; never expose the debugger publicly.

Request and Inertia captures redact sensitive fields and URL query values. Logs preserve original diagnostic values
and are not redacted by the capture policy; SQL diagnostics can include substituted query values. Treat stored captures
as sensitive and review them before sharing. Event context and source traces are disabled by default.

## Documentation

- [Default configuration options](config/params.php)
- [Changelog](CHANGELOG.md)

## Package information

[![PHP](https://img.shields.io/badge/%3E%3D8.3-777BB4.svg?style=for-the-badge&logo=php&logoColor=white)](https://www.php.net/releases/8.3/en.php)
[![Yii3](https://img.shields.io/badge/Yii-3-0073AA.svg?style=for-the-badge&logo=yii&logoColor=white)](https://github.com/yiisoft)
[![Total Downloads](https://img.shields.io/packagist/dt/yii3/debug.svg?style=for-the-badge&logo=composer&logoColor=white&label=Downloads)](https://packagist.org/packages/yii3/debug)

## Project status

[![Codecov](https://img.shields.io/codecov/c/github/yii3/debug.svg?style=for-the-badge&logo=codecov&logoColor=white&label=Coverage)](https://codecov.io/github/yii3/debug)
[![PHPStan Level Max](https://img.shields.io/badge/PHPStan-Level%20Max-4F5D95.svg?style=for-the-badge&logo=github&logoColor=white)](https://github.com/yii3/debug/actions/workflows/static.yml)
[![Quality](https://img.shields.io/github/actions/workflow/status/yii3/debug/quality.yml?style=for-the-badge&label=Quality&logo=github)](https://github.com/yii3/debug/actions/workflows/quality.yml)
[![Code Style](https://img.shields.io/github/actions/workflow/status/yii3/debug/ecs.yml?style=for-the-badge&label=Code%20Style&logo=github)](https://github.com/yii3/debug/actions/workflows/ecs.yml)

## Our social networks

[![Follow on X](https://img.shields.io/badge/-Follow%20on%20X-1DA1F2.svg?style=for-the-badge&logo=x&logoColor=white&labelColor=000000)](https://x.com/Terabytesoftw)
[![Follow on Facebook](https://img.shields.io/badge/-Follow%20on%20Facebook-1877F2.svg?style=for-the-badge&logo=facebook&logoColor=white&labelColor=000000)](https://www.facebook.com/wilmer.arambula.9)
[![Join our Subreddit](https://img.shields.io/badge/-Join%20our%20Subreddit-FF4500.svg?style=for-the-badge&logo=reddit&logoColor=white&labelColor=000000)](https://www.reddit.com/r/Yii2/)
[![Join on Telegram](https://img.shields.io/badge/-Join%20on%20Telegram-26A5E4.svg?style=for-the-badge&logo=telegram&logoColor=white&labelColor=000000)](https://t.me/yii_framework_in_english)

## License

[![License](https://img.shields.io/badge/License-BSD--3--Clause-brightgreen.svg?style=for-the-badge&logo=opensourceinitiative&logoColor=white&labelColor=555555)](LICENSE)
