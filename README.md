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

The drawer moves focus to its close control and restores the activating chip when closed. Use `Escape` to close it,
or resize it from the keyboard with `ArrowUp`, `ArrowDown`, `Home`, and `End` on the separator. Eligible HTML
redirect and error responses remain inspectable; bodyless, AJAX, and non-HTML responses are captured without markup.

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

## Dump, Mail, and Queue panels

The Dump panel is always available. Inject `Yii3\Debug\Collector\DumpCollector` and submit values explicitly during
the request:

```php
final readonly class ExampleAction
{
    public function __construct(private \Yii3\Debug\Collector\DumpCollector $dumpCollector) {}

    public function __invoke(): void
    {
        $this->dumpCollector->collect(['phase' => 5, 'ready' => true]);
    }
}
```

The Mail panel is registered when `yiisoft/mailer` is installed. Wrap the application's concrete mailer so successful
and failed messages are captured after the transport reports its outcome:

```php
use Yii3\Debug\Integration\MailerInterfaceProxy;
use Yiisoft\Definitions\Reference;
use Yiisoft\Mailer\{FileMailer, MailerInterface};

return [
    MailerInterfaceProxy::class => [
        '__construct()' => [
            'decorated' => Reference::to(FileMailer::class),
        ],
    ],
    MailerInterface::class => MailerInterfaceProxy::class,
];
```

Captured `.eml` files are stored under `yii3/debug.mail.path`. Downloads require an allowed client IP plus a valid
snapshot tag and message sequence; arbitrary file names are never accepted by the route.

The Queue panel is registered when `yiisoft/queue` is installed. Decorate the concrete producer, and optionally the
worker, while keeping the public interfaces bound to the decorators:

```php
use App\Queue\{AppQueueProducer, AppQueueWorker};
use Yii3\Debug\Integration\{QueueProducerDecorator, QueueWorkerDecorator};
use Yiisoft\Definitions\Reference;
use Yiisoft\Queue\QueueProducerInterface;
use Yiisoft\Queue\Worker\WorkerInterface;

return [
    QueueProducerDecorator::class => [
        '__construct()' => [
            'decorated' => Reference::to(AppQueueProducer::class),
        ],
    ],
    QueueProducerInterface::class => QueueProducerDecorator::class,
    QueueWorkerDecorator::class => [
        '__construct()' => [
            'decorated' => Reference::to(AppQueueWorker::class),
        ],
    ],
    WorkerInterface::class => QueueWorkerDecorator::class,
];
```

Queue payload values under `accessToken`, `apiKey`, `authorization`, `password`, `refreshToken`, `secret`, and `token`
are redacted by default. Replace `yii3/debug.queue.redactedProperties` to use an application-specific exact,
case-insensitive key list.

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
