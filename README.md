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
    <a href="https://dashboard.stryker-mutator.io/reports/github.com/yii3/debug/main" target="_blank">
        <img src="https://img.shields.io/endpoint?style=for-the-badge&url=https%3A%2F%2Fbadge-api.stryker-mutator.io%2Fgithub.com%2Fyii3%2Fdebug%2Fmain" alt="Mutation Testing">
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

<p align="center">
    <picture>
        <source media="(prefers-color-scheme: dark)" srcset="docs/images/home-dark.png">
        <source media="(prefers-color-scheme: light)" srcset="docs/images/home-light.png">
        <img src="docs/images/home-light.png" alt="Yii3 application with the debug toolbar">
    </picture>
</p>

> [!WARNING]
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

Run the application with `APP_ENV=dev`. The debugger also accepts `debug` and `test`; it stays disabled when the
runtime environment is missing, unknown, or production. Setting the runner's configuration environment alone is
not enough.

Rebuild the merged configuration after installing or updating the package:

```shell
composer yii-config-rebuild
```

Applications that build their middleware list by hand must spread the merged
`$params['yiisoft/middleware-dispatcher']['middlewares']` first, so the toolbar middleware stays in the pipeline.
The middleware also answers the `/debug` pages itself, so the debugger publishes no routes and needs none of your
application's routing.

The debugger attaches itself to `Yiisoft\Db\Connection\ConnectionInterface`, `Psr\Log\LoggerInterface`, and
`Psr\EventDispatcher\EventDispatcherInterface` through the container, using the `di-providers` and `bootstrap`
configuration groups; both must belong to the provider and bootstrap groups your runner loads. Your services keep
their own definitions and the debugger only decorates them, so a custom PSR logger or dispatcher needs no separate
integration. Attaching the log target rebuilds `Yiisoft\Log\Logger`, which resets its flush interval and context
provider to the defaults; configure either where the logger service is defined.

The snapshot itself is written when the application dispatches `Yiisoft\Yii\Http\Event\ApplicationShutdown`, through
the merged `events-web` listeners the Yii HTTP runner uses. Finalizing there, rather than inside the middleware
pipeline, is what makes the logs, the profiler spans flushed after emission, and the queries issued while the view is
rendered lazily complete in the capture. See [Capture lifecycle](docs/configuration.md#capture-lifecycle).

### Basic usage

Open an application page, expand the toolbar at the bottom, and select a panel chip to inspect the request.
Use the Yii chip for Configuration and the PHP chip for PHP info. Switch between light and dark themes from the
toolbar, and press `Escape` to close the drawer.

Open `/debug` to browse retained requests. Select two captures in History to compare request metrics and panel
changes, then open either capture for its details. Comparison shows structural counts without exposing panel values.

## Configuration

The debugger runs with its default options out of the box. See the
[configuration reference](docs/configuration.md) for the Inertia and Vite integrations, custom collectors and panels,
database thresholds, and IDE links.

## Security

The toolbar and debugger routes allow `127.0.0.1` and `::1` by default. Access checks use the direct client address,
not forwarded proxy headers. Add only trusted development addresses to `allowedIPs`; never expose the debugger publicly.

Request and Inertia captures redact sensitive fields and URL query values. Logs preserve original diagnostic values
and are not redacted by the capture policy; SQL diagnostics can include substituted query values. Treat stored captures
as sensitive and review them before sharing. In the Events panel, context capture and source traces are disabled by
default; this does not affect source traces in Logs or Database.

## Documentation

- [Configuration reference](docs/configuration.md)
- [Panel screenshots](docs/screenshots.md)
- [Default configuration options](config/params.php)

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
