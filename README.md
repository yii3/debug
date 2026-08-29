# Yii3 Debug

Minimal Yii3 adapter for [`php-forge/debug-core`](https://github.com/php-forge/debug-core).

This foundational release provides only:

- a protected request-history page with summary counters, filtering, pagination, and the Yii-style grid;
- minimal filesystem persistence for request summaries;
- the Yii version chip linked to the live Configuration page;
- the PHP version chip linked to the Debug Core phpinfo page;
- the shared Yii-style page shell with the current-request card, History as the only primary sidebar item, and the
  complete top brand bar;
- AJAX request tracking;
- the injected debug toolbar.

It intentionally contains no request diagnostic panels, collectors, framework instrumentation, optional integrations,
or identity switching. Query and mail capture are deferred to later phases.

## Installation

```shell
composer require yii3/debug --dev
```

With Yii Config Plugin enabled, the package contributes its parameters, DI definitions, protected history and
brand-page routes, toolbar-data route, and toolbar middleware. Applications do not need to reference a `Yii3\Debug`
class in their own configuration.

The package contributes `ToolbarMiddleware` through the recursive `yiisoft/middleware-dispatcher.middlewares` parameter.
The application should build its dispatcher from the merged middleware parameters once.

## Configuration

Override only the toolbar values the application needs:

```php
return [
    'yii3/debug' => [
        'application' => [
            'name' => 'My application',
            'version' => '1.0.0',
            'language' => 'en',
            'sourceLanguage' => 'en',
            'charset' => 'UTF-8',
            'env' => 'dev',
            'debug' => true,
        ],
        'allowedIPs' => ['127.0.0.1', '::1'],
        'historySize' => 50,
        'routePrefix' => '/debug',
        'storage' => [
            'path' => '@runtime/debug',
            'dirMode' => 0o700,
            'fileMode' => 0o600,
        ],
        'toolbar' => [
            'skipUrls' => [],
            'position' => 'bottom',
            'height' => 50,
        ],
    ],
];
```

`skipUrls` contains same-origin URLs that the toolbar runtime should omit from AJAX tracking.
Application metadata is optional; neutral values are used when it is omitted.
`historySize` limits retained request summaries. The default storage directory is resolved through Yii aliases.

## Access control

The history, Configuration, phpinfo, and toolbar-data routes accept `127.0.0.1` and `::1` by default. The routes use
Yii's official `Yiisoft\Yii\Middleware\IpFilter`; toolbar injection uses the same
`Yiisoft\NetworkUtilities\IpRanges` configuration. This initial phase authorizes the direct `REMOTE_ADDR` value only.

## Scope

The package stores only the request metadata required by the History grid and sidebar: tag, method, URL, IP, status,
time, AJAX state, duration, and peak memory. It also adds `X-Debug-Tag` and `X-Debug-Duration` response headers so the
shared toolbar runtime can display AJAX activity. Snapshot panel payloads remain empty; collectors and request panels
will be introduced independently in later phases.
