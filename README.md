# Yii3 Debug

Minimal Yii3 adapter for [`php-forge/debug-core`](https://github.com/php-forge/debug-core).

This foundational release provides only:

- the Yii and PHP version chips;
- AJAX request tracking;
- the injected debug toolbar.

It intentionally contains no debug page, request history, persistent storage, diagnostic panels, framework
instrumentation, optional integrations, identity switching, or runtime package discovery.

## Installation

```shell
composer require yii3/debug --dev
```

With Yii Config Plugin enabled, the package contributes its parameters, DI definitions, protected toolbar-data route,
and toolbar middleware. Applications do not need to reference a `Yii3\Debug` class in their own configuration.

The package contributes `ToolbarMiddleware` through the recursive `yiisoft/middleware-dispatcher.middlewares` parameter.
The application should build its dispatcher from the merged middleware parameters once.

## Configuration

Override only the toolbar values the application needs:

```php
return [
    'yii3/debug' => [
        'allowedIPs' => ['127.0.0.1', '::1'],
        'routePrefix' => '/debug',
        'toolbar' => [
            'skipUrls' => [],
            'position' => 'bottom',
            'height' => 50,
        ],
    ],
];
```

`skipUrls` contains same-origin URLs that the toolbar runtime should omit from AJAX tracking.

## Access control

The toolbar and its data route accept `127.0.0.1` and `::1` by default. The route uses Yii's official
`Yiisoft\Yii\Middleware\IpFilter`; toolbar injection uses the same `Yiisoft\NetworkUtilities\IpRanges` configuration.
This initial phase authorizes the direct `REMOTE_ADDR` value only.

## Scope

The package does not store requests. It adds `X-Debug-Tag` and `X-Debug-Duration` response headers so the shared
toolbar runtime can display AJAX activity. Collectors, storage, history, and panels may be introduced later from this
small, verifiable base.
