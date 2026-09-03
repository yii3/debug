# Yii3 Debug

Minimal Yii3 adapter for [`php-forge/debug-core`](https://github.com/php-forge/debug-core).

The package provides:

- a protected request-history page with summary counters, filtering, pagination, and the Yii-style grid;
- a protected capture-comparison workflow with request-metric deltas and privacy-preserving structural counts;
- minimal filesystem persistence for request summaries and redacted Request snapshots;
- the Yii version chip linked to the live Configuration page;
- the PHP version chip linked to the Debug Core phpinfo page;
- the shared Yii-style page shell with the current-request card, primary History and Request navigation, and the
  complete top brand bar;
- the built-in Request toolbar status, hero, routing and parameter sections, request/response headers, session and
  server tabs, using the same Debug Core presentation as Yii2;
- the built-in Profiling toolbar time and peak-memory metrics, plus a unified Timeline and table over spans recorded
  through `yiisoft/profiler`;
- optional extension-panel navigation that is populated only by data captured for the selected request;
- an Inertia toolbar chip and detail panel with the captured component, page metadata, visit type, shared/page prop
  origins, negotiation headers, version-conflict diagnostics, and redacted raw payload when explicitly registered;
- a Vite toolbar mode chip and detail panel with native development or production configuration, entrypoints, and typed
  build-manifest chunks when explicitly registered;
- AJAX request tracking;
- the injected debug toolbar.

Other diagnostic panels, framework instrumentation, and identity switching are outside the current scope.

## Installation

```shell
composer require yii3/debug --dev
```

With Yii Config Plugin enabled, the package contributes its parameters, DI definitions, protected history,
comparison, and brand-page routes, toolbar-data route, and toolbar middleware. The base debugger requires no
application-owned DI definitions.

The package contributes `ToolbarMiddleware` through the recursive `yiisoft/middleware-dispatcher.middlewares` parameter.
The application should build its dispatcher from the merged middleware parameters once.

Extensions are opt-in. The package does not inspect installed classes or interfaces and does not connect itself to
optional packages at runtime. Applications explicitly compose the collectors, panels, and protocol bridges they use.

## Configuration

Override only the debugger values the application needs:

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
        'viewPath' => '@yii3DebugViews',
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
`viewPath` accepts any registered Yii alias. Override `@yii3DebugViews` through `yiisoft/aliases.aliases`, or point
`viewPath` at an application-owned template directory, to customize the shared debugger views.

### Profiling

The Profiling collector is enabled by default. Every captured request records its total processing time and peak
memory usage. Inject Yii's profiler into a controller or service to add named spans to the unified Profiling view:

```php
<?php

declare(strict_types=1);

namespace App\Web;

use Psr\Http\Message\ResponseInterface;
use Yiisoft\Profiler\ProfilerInterface;

final readonly class HomeAction
{
    public function __construct(private ProfilerInterface $profiler) {}

    public function __invoke(): ResponseInterface
    {
        $context = ['category' => self::class . '::__invoke'];

        $this->profiler->begin('Build home response', $context);

        try {
            return $this->buildResponse();
        } finally {
            $this->profiler->end('Build home response', $context);
        }
    }
}
```

The token and category passed to `begin()` and `end()` must match. Completed spans retain their nesting, duration,
memory delta, category, and token in the captured snapshot.

Profiling is the single performance entry in the debugger sidebar. One screen presents the request-relative
**Timeline** first and the sortable, paginated details table below it. Minimum-duration, category, and information
filters apply to both representations, while sorting and pagination affect only the detailed table. The Timeline keeps
the complete filtered capture order and includes a memory curve composed from profiler samples and available log
samples. Class-like categories show only their short class name on one line and preserve the full category and method
as hover text. Filter results remain shareable through the canonical `Profile[...]` URL parameters.

### Inertia extension

The Inertia collector and panel are framework-neutral services in this package. The application owns the small bridge
to its Inertia adapter, so `yii3/debug` has no runtime dependency on that adapter. For an application using
`yii3/inertia`, add an application-local observer:

```php
<?php

declare(strict_types=1);

namespace App\Debug;

use PHPForge\Inertia\Page;
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Inertia\ResolvedPageObserverInterface;

final readonly class InertiaPageObserver implements ResolvedPageObserverInterface
{
    public function __construct(private InertiaCollector $collector) {}

    public function observe(Page $page): void
    {
        $this->collector->observe($page->toArray(), $page->sharedProps());
    }
}
```

Then register both sides in the application's development DI configuration:

```php
<?php

declare(strict_types=1);

use App\Debug\InertiaPageObserver;
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Panel\InertiaPanel;
use Yii3\Inertia\ResolvedPageObserverInterface;

return [
    ExtensionRegistry::class => static fn(
        InertiaCollector $collector,
        InertiaPanel $panel,
    ): ExtensionRegistry => new ExtensionRegistry(
        collectors: [$collector],
        panels: [$panel],
    ),
    ResolvedPageObserverInterface::class => static fn(
        InertiaCollector $collector,
    ): InertiaPageObserver => new InertiaPageObserver($collector),
];
```

Load these definitions only in the development environment that installs `yii3/debug`; production configuration must
not reference a package removed by `composer install --no-dev`. No runtime symbol-discovery guard is needed. Requests
without Inertia activity remain absent from the Extensions group, and the toolbar chip appears only when the capture
contains a component, matching the Yii2 debugger behavior.

### Vite extension

The Vite collector consumes the same immutable configuration and entrypoints as the native
[`php-forge/vite`](https://github.com/php-forge/vite) service. Register both services from the application's development
configuration so the collector can inspect the public Vite API without reflection or manual JSON parsing:

```php
<?php

declare(strict_types=1);

use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use PHPForge\Vite\Manifest\ManifestLoader;
use PHPForge\Vite\Vite;
use Yii3\Debug\Collector\ViteCollector;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Panel\VitePanel;
use Yiisoft\Definitions\Reference;

$entrypoints = ['resources/js/app.ts'];
$configuration = DevelopmentConfiguration::create('http://127.0.0.1:5173');

return [
    Vite::class => static fn(): Vite => Vite::create($configuration, $entrypoints),
    ViteCollector::class => [
        '__construct()' => [
            'configuration' => $configuration,
            'entrypoints' => $entrypoints,
            'manifestLoader' => Reference::optional(ManifestLoader::class),
        ],
    ],
    ExtensionRegistry::class => static fn(
        ViteCollector $collector,
        VitePanel $panel,
    ): ExtensionRegistry => new ExtensionRegistry(
        collectors: [$collector],
        panels: [$panel],
    ),
];
```

Use the same pattern with `ProductionConfiguration`. Production captures load the configured manifest through the
native `ManifestLoader`, preserving Vite's validation and typed chunk metadata. A missing or invalid manifest becomes
an isolated panel failure instead of a silent fallback. Development captures record configuration only and never
contact the Vite development server.

An application using both extensions must compose them in the same registry rather than define the registry twice:

```php
ExtensionRegistry::class => static fn(
    InertiaCollector $inertiaCollector,
    InertiaPanel $inertiaPanel,
    ViteCollector $viteCollector,
    VitePanel $vitePanel,
): ExtensionRegistry => new ExtensionRegistry(
    collectors: [$inertiaCollector, $viteCollector],
    panels: [$inertiaPanel, $vitePanel],
),
```

## Access control

The history, capture-comparison, Configuration, phpinfo, and toolbar-data routes accept `127.0.0.1` and `::1` by
default. The routes use Yii's official `Yiisoft\Yii\Middleware\IpFilter`; toolbar injection uses the same
`Yiisoft\NetworkUtilities\IpRanges` configuration. This initial phase authorizes the direct `REMOTE_ADDR` value only.

## Captured data

The package stores the request metadata required by the History grid and sidebar: tag, method, redacted URL, IP,
status, time, AJAX state, duration, and peak memory. Its built-in Profiling snapshot stores the request metrics,
completed spans, and their memory samples. The Timeline section derives its request origin from the existing
request summary and can enrich the curve from a captured Log payload, so it does not persist a duplicate Timeline
payload. Its built-in Request snapshot also records the matched route and action, route parameters, GET/POST/file
buckets, request body, request/response headers, and redacted server data in the shared Yii2-compatible shape. Empty
session and flash buckets retain the standard Session tab without mutating application session state. It also adds
`X-Debug-Tag`, `X-Debug-Duration`, and `X-Debug-Link` response headers so the
shared toolbar runtime can display AJAX activity and open the captured Request panel.

With the Inertia extension explicitly registered, each snapshot also stores its Inertia context: the resolved page,
shared prop keys, negotiation headers, response status, and reload location. Captures without Inertia activity remain
hidden from the Extensions group but can still explain the missing page when addressed directly. The shared capture
policy redacts sensitive values and URL query parameters before persistence. Capture comparison reports request-summary
deltas and privacy-preserving structural panel counts without exposing captured values.

With the Vite extension explicitly registered, each active capture stores its normalized entrypoints, runtime mode,
public development or production settings, and the production manifest's chunk names, output files, CSS/import counts,
and entrypoint flags. The collector retains no asset contents and does not execute inline-module providers.
