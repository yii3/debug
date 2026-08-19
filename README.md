# Yii3 Debug

Yii3 adapter for [`php-forge/debug-core`](https://github.com/php-forge/debug-core).

The adapter implements Yii3 collectors locally and stores their typed output in the same versioned snapshot format
used by the Yii2 adapter.

## Installation

```shell
composer require yii3/debug --dev
```

The package contributes the shared snapshot store plus `/debug`, `/debug/view`, and `/debug/toolbar` routes
automatically when Yii Config Plugin is enabled.

Register `Yii3\Debug\Middleware\ToolbarMiddleware` as the first application middleware so it wraps the error handler
and can inject the toolbar into every eligible HTML response:

```php
'withMiddlewares()' => [
    [
        \Yii3\Debug\Middleware\ToolbarMiddleware::class,
        \Yiisoft\ErrorHandler\Middleware\ErrorCatcher::class,
        // Remaining application middleware.
    ],
],
```

The toolbar and debug pages accept requests from `127.0.0.1` and `::1` by default. Configure `allowedIPs` explicitly
when the development application runs behind a trusted container or proxy.

The default path is `runtime/debug`, relative to the application's working directory. Override it in application
parameters when an absolute runtime path is required:

```php
return [
    'yii3/debug' => [
        'path' => '/absolute/path/to/runtime/debug',
        'historySize' => 50,
        'dirMode' => 0775,
        'fileMode' => 0664,
        'allowedIPs' => ['127.0.0.1', '::1'],
    ],
];
```

## Custom collectors and panels

Implement `PHPForge\Debug\Collector\CollectorInterface` in the application and return a typed
`PHPForge\Debug\Storage\PanelSnapshot`. An optional `Yii3\Debug\Panel\PanelInterface` with the same stable ID can
provide navigation metadata and render the stored payload. Add both class names to the package DI configuration:

```php
return [
    'yii3/debug' => [
        'collectors' => [
            \App\Debug\ExampleCollector::class,
        ],
        'panels' => [
            \App\Debug\ExamplePanel::class,
        ],
    ],
];
```

For example, both classes may return `app.example` from `id()`. Package DI converts these explicit class lists to
container references and retains the built-in `request` collector and panel. Stored payloads without a registered
panel remain inspectable through the escaped JSON fallback.

Panels that need the current tag, query parameters, theme, or adapter-generated URLs may additionally implement
`Yii3\Debug\Panel\ContextAwarePanelInterface`. The renderer then calls `renderWithContext()` with a
`PHPForge\Debug\Panel\PanelRenderContext`; the original `PanelInterface::render()` contract remains available as the
context-free fallback and existing panels require no changes.

## Architecture

- `php-forge/debug-core` owns collector contracts and coordination, failure isolation, strict snapshot hydration, JSON
  persistence, the request manifest, shared filter and pagination contracts, panel render context, frontend
  primitives, and portable toolbar data contracts.
- `yii3/debug` owns PSR-7 request collection, Yii3 DI and middleware lifecycle wiring, optional panel renderers, routes,
  actions, URL mapping, asset publication, and response injection.
- The adapter resolves shared templates through `@yii3DebugViews` and renders them with `Yiisoft\View\WebView`; Core
  does not register framework assets or render framework responses.

## License

The package is released under the BSD-3-Clause license. See `LICENSE`.
