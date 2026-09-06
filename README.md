# Yii3 Debug

Minimal Yii3 adapter for [`php-forge/debug-core`](https://github.com/php-forge/debug-core).

The package provides:

- a protected request-history page with summary counters, filtering, pagination, and the Yii-style grid;
- a protected capture-comparison workflow with request-metric deltas and privacy-preserving structural counts;
- minimal filesystem persistence for request summaries and redacted Request snapshots;
- the Yii version chip linked to the live Configuration page;
- the PHP version chip linked to the Debug Core phpinfo page;
- the shared Yii-style page shell with the current-request card, primary History, Request, Logs, Events, and Profiling
  navigation, and the complete top brand bar;
- the built-in Request toolbar with the resolved route followed by HTTP status, plus Debug Core's shared request-and-route
  execution overview, canonical input and metadata tabs, and a filterable live route inventory;
- the built-in Logs toolbar counters and filterable message grid, including severity shortcuts, removable active
  filters, IDE-linked source traces, and request-scoped memory samples;
- the built-in Events counter and filterable PSR-14 dispatch metadata, without retaining event object payloads;
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
comparison, and brand-page routes, toolbar-data route, and toolbar middleware. Logs and PSR-14 event capture are also configured by the package; no copied DI factories are needed.

The package contributes `ToolbarMiddleware` through the recursive `yiisoft/middleware-dispatcher.middlewares` parameter.
The application should build its dispatcher from the merged middleware parameters once.

Extensions are opt-in. The package does not inspect installed classes or interfaces and does not connect itself to
optional packages at runtime. Applications enable the built-in integrations through parameters or explicitly compose custom extensions.

## Automatic configuration

Set the application's Yii Config `config-plugin-options.vendor-override-layer` to `yii3/debug`. This is the
standard vendor override layer, allowing the debugger to replace Yii's dispatcher binding without duplicate vendor
keys. The package contributes directly to `params`, `di`, `di-web`, and `routes`; no custom groups or manual
`$config->get()` calls are needed. Reinstall/update the package and run `composer yii-config-rebuild` after changing
configuration metadata.

The package activates only when `APP_ENV` is `debug`, `dev`, or `test`. It reads the process environment first and then
`$_SERVER`; missing, production, or unknown environments contribute no debugger configuration. Set `APP_ENV` at runtime,
not only the runner's configuration environment. Production deployments must still use `composer install --no-dev`.

The logger uses the merged `yiisoft/log.targets` parameters, including named `debug` and `stream` targets. If the
application defines its own Yii logger, build its targets with `ReferencesArray::from($params['yiisoft/log']['targets'])`
so package targets are preserved. Existing custom PSR loggers and dispatchers remain application-owned integrations.
Yii applications normally supply the listener provider through `yiisoft/yii-event`; the debugger decorates the concrete
Yii dispatcher without recursively requesting `EventDispatcherInterface`.

For an Inertia/Vite application, enable the desired extensions in application parameters:

```php
'yii3/debug' => [
    'extensions' => [
        'inertia' => true,
        'vite' => true,
    ],
],
```

Inertia requires `yii3/inertia ^0.1`. Vite uses the application's existing `php-forge/vite` configuration and entrypoints.
The package registers collectors, panels, and the resolved-page observer automatically; neither extension is enabled
by default, and no optional classes are discovered at runtime. A custom `ExtensionRegistry` definition can override
the package's registry when additional extensions are needed.

When developing this library, its generated merge plan belongs to `runtime/.merge-plan.php`, which is ignored by Git.
Applications keep their own merge-plan location.

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

### Request

The Request collector and panel are enabled by default. When the request resolves to a named route, the Request toolbar
chip shows that route first and the HTTP status second; requests without a resolved route show only the status.

Debug Core renders the request and route as one persistent execution overview instead of splitting the same identity
across Request and Router panels. The overview keeps the method, URL, status, resolved route, dispatched action, and
duration visible while browsing diagnostics. When available, it also presents the matched pattern, HTTP methods, hosts,
middleware descriptors, and route parameters captured with the selected request.

The canonical tab order is **Input**, **Headers**, optional **Session**, **Routes (N)**, and **Server**. The Routes tab is
added when the current application route collection is available; it includes source provenance, a filter, and an
explicit marker for the route that handled the selected request. The inventory reflects the currently running
application configuration and is not persisted with the capture, so it may differ from routes registered when a
historical request was handled. The adapter does not synthesize Yii2 URL-rule traces or scan controller files for
possible actions; it shows only matched and registered data exposed by Yii3's router.

Server shows additional diagnostics without a second execution summary. Exact duplicates of the Request overview
and inbound headers move to the collapsed **Raw server variables** disclosure, which preserves every captured key
and value. Differences and unknown values remain visible; each group has an independent filter.

Input and Session sections use the shared disclosure: populated sections open by default, empty sections stay
collapsed, and each populated section has its own filter. Session data and Flashes can be searched independently.

Routes use the same expandable ledger as the Yii2 adapter. Each row keeps methods, pattern, and route identity visible;
its full-width details expose action, host, and middleware metadata. Filtering searches those details and restores
their previous open state when cleared. Internal `yii3-debug/` routes are omitted regardless of their URL prefix.

### Logs

The Logs collector and panel are enabled by default, including the default Yii logger integration.
For custom logger definitions, add the container-managed `DebugLogTarget` to the targets of the
application logger in the development environment so the collector receives the same messages as the other log
targets:

```php
<?php

declare(strict_types=1);

use Psr\Log\LoggerInterface;
use Yii3\Debug\Log\DebugLogTarget;
use Yiisoft\Definitions\ReferencesArray;
use Yiisoft\Log\{Logger, StreamTarget};

return [
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from([
                DebugLogTarget::class,
                StreamTarget::class,
            ]),
        ],
    ],
];
```

Keep any existing application targets in that list. The debug target is reset at the beginning and end of every
captured request. When that exact target belongs to a `Yiisoft\Log\Logger`, the collector flushes the logger before
snapshotting so pending messages are not lost. Applications using a different PSR logger need an application-owned
bridge that forwards messages to this target.

The summary is calculated from the complete capture, while `Log[level]`, `Log[category]`, and `Log[message]` filter the
grid. Nonzero severity counters provide direct level filters. Every active filter is displayed in a removable pill,
and **Clear all** removes only the Logs filter group while retaining the current capture and unrelated URL state.

### Events

The Events collector, panel, and Yii dispatcher decorator are configured automatically. PSR-14 has no global wildcard listener. Route the
application's `EventDispatcherInterface` through the debug decorator in development so it can observe every object
sent through that interface. When the application uses Yii's concrete dispatcher, register the non-recursive binding
like this:

```php
<?php

declare(strict_types=1);

use Psr\EventDispatcher\EventDispatcherInterface;
use Yii3\Debug\Collector\EventCollector;
use Yii3\Debug\Event\DebugEventDispatcher;
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;

return [
    EventDispatcherInterface::class => static fn(
        Dispatcher $dispatcher,
        EventCollector $collector,
    ): EventDispatcherInterface => new DebugEventDispatcher($dispatcher, $collector),
];
```

The factory deliberately requests the concrete Yii `Dispatcher`. Do not request `EventDispatcherInterface` from the
factory that defines that same interface, because the container would recursively resolve the decorator. Load this
binding only from development configuration; production configuration must not reference `yii3/debug`, which is
installed as a development dependency.

The decorator records each event immediately before delegating and leaves the delegate's returned object, listener
order, stoppable propagation, nested dispatches, and exceptions unchanged. Nested events are visible when they are
also dispatched through the decorated interface; code that calls the concrete dispatcher directly bypasses the
decorator.

By default, each row stores the dispatch timestamp, event FQCN, and a scalar source label, plus optional scalar
lifecycle correlation metadata for known middleware markers. For Yii middleware lifecycle events,
the source is the middleware class returned by the event's documented `getMiddleware()` accessor. An anonymous action
wrapper generated by Yii's middleware factory may expose a `[class, method]` callback through `__debugInfo()`; only that
exact shape is accepted and rendered as `Class::method`, while all other debug metadata is discarded. Other events use
the immediate class whose method invoked the decorated dispatcher, and calls made outside class scope leave the source
empty. This is a best-effort diagnostic because PSR-14 has no native Yii2-style sender.

The Yii3 table presents #, Time, Event, and Source instead of repeating the Yii2-specific Name, Class,
Sender, and Static fields. Event cells contain native diagnostic disclosures; column filters and sorting remain directly available. No middleware, request, response, listener result, or event object is persisted, and no generic property traversal
is performed. Selected lifecycle context and file/line traces can be enabled explicitly as described below. Default metadata capture does not apply redaction. Opt-in context is sanitized before persistence. The toolbar shows one total counter only when at least one event was dispatched; an empty
active capture remains available in the Events panel with its PSR-14-specific empty state.

#### Event table and opt-in diagnostics

The single table keeps original observation numbers, elapsed offsets, and gaps across filtering, sorting, and pagination.
Expanded diagnostics show context and source trace side by side without repeating the row fields.
Event/source group shortcuts count the whole capture. No separate execution-flow list is rendered. Known
middleware entry/leave markers are correlated by object identity and per-object nesting, never by class name.
Completed pairs show a monotonic inclusive interval, including nested work and dispatch overhead. Neither a leave
marker nor an interval proves success; listeners, exclusive timings, and final propagation results are not captured.

Context and argument-free source traces are disabled by default. Override the collector factory in development DI:

```php
use Yii3\Debug\Collector\EventCollector;

return [
    EventCollector::class => static function (): EventCollector {
        $collector = new EventCollector();
        $collector->captureContext = true;
        $collector->traceLimit = 8;

        return $collector;
    },
];
```

Selected lifecycle context contains the request method/path without query values, or the observed response status.
No headers, cookies, bodies, arbitrary event properties, or listener results are traversed. Unknown event types
report unsupported context. Context is redacted and bounded to sixteen fields of 2,048 bytes; traces retain at most
sixteen file/line locations, without arguments or objects. Failures are visible in the event details. Request-local
correlation state is cleared between worker requests and does not retain middleware objects strongly.

Old snapshots remain readable but cannot acquire missing diagnostics retroactively. Upgrade the core and adapter
together because older readers reject undeclared diagnostic fields.

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

Enable `yii3/debug.extensions.inertia` in application parameters. The package registers the collector, panel, and
`Yii3\Inertia\ResolvedPageObserver` automatically. This named adapter delegates payload/shared-key forwarding to
`PHPForge\Inertia\ResolvedPageObserver`, while preserving Yii3's existing observer interface. Applications need no
anonymous classes, protocol bridges, or copied DI definitions.

Use the updated local/published `yii3/inertia` revision that provides the named observer and requires the core 0.3
line. Older adapter revisions do not provide this class. The consuming application must update its lock before
using this debugger integration.

For a custom composition, the observer can be constructed directly:

```php
use Yii3\Debug\Collector\InertiaCollector;
use Yii3\Inertia\ResolvedPageObserver;

$collector = new InertiaCollector();
$observer = new ResolvedPageObserver($collector->observe(...));
```

Register this same collector instance with the debugger's collector coordinator; if it is already managed by your
container, reuse that instance instead of constructing a second collector.

Requests without Inertia activity remain absent from the Extensions group. The toolbar chip appears only when the
capture contains a component. Existing captures are not rewritten.

### Vite extension

Enable `yii3/debug.extensions.vite` to wire the Vite integration automatically from application parameters.
For a custom composition, the Vite collector consumes the same immutable configuration and entrypoints as the native
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
status, time, AJAX state, duration, and peak memory. Its built-in Logs snapshot stores the captured message, level,
category, timestamp, source trace, and memory reading for each entry. Its built-in Events snapshot stores the
dispatch timestamp, event FQCN, resolved scalar source label, and optional lifecycle correlation. Explicitly enabled
inspection adds only bounded selected context and argument-free traces, never an event object dump. Its
built-in Profiling snapshot stores the request metrics, completed spans, and their memory samples. The Timeline section
derives its request origin from the existing request summary and can enrich the curve from captured Logs, so it does
not persist a duplicate Timeline payload. Its built-in Request snapshot also records the matched route, its
persistence-safe definition and action, route parameters, GET/POST/file buckets, request body, request/response headers,
and redacted server data for Debug Core's shared execution overview. The Routes inventory is read from the current
application configuration instead of being persisted with each capture. Empty session and flash buckets retain the
standard Session tab without mutating application session state. It also adds `X-Debug-Tag`, `X-Debug-Duration`, and
`X-Debug-Link` response headers so the shared toolbar runtime can display AJAX activity and open the captured Request
panel.

With the Inertia extension explicitly registered, each snapshot also stores its Inertia context: the resolved page,
shared prop keys, negotiation headers, response status, and reload location. Captures without Inertia activity remain
hidden from the Extensions group but can still explain the missing page when addressed directly. The shared capture
policy redacts sensitive values and URL query parameters before persistence. Capture comparison reports request-summary
deltas and privacy-preserving structural panel counts without exposing captured values.

With the Vite extension explicitly registered, each active capture stores its normalized entrypoints, runtime mode,
public development or production settings, and the production manifest's chunk names, output files, CSS/import counts,
and entrypoint flags. The collector retains no asset contents and does not execute inline-module providers.

## History comparison architecture

`HistoryComparison::fromSnapshots()` delegates panel selection, ordering, failure precedence, capture states, and
combined structural/state counts to Debug Core's `PHPForge\Debug\Comparison\PanelComparison`, which reuses
`PayloadDifference`. Request-summary metrics remain delegated to `SummaryMetricComparison`. The adapter only maps
these results into its existing public models, including `HistoryPanelComparison`,
`HistoryPanelStates`, and `HistoryPanelDifferenceCounts`. No constructor, property, getter, return type,
captured value, or storage format changes. Metric labels, order, units, precision, separators, signs, percentages, trends,
and panel IDs retain their exact previous behavior, including missing values, zero baselines, and unrounded float deltas.

Publish the Core revision providing `SummaryMetricComparison` and `PanelComparison` first and update consuming application
locks before installing this adapter revision. The existing `^0.1@dev` constraint also admits older development revisions without the
new classes; local workspace links do not guarantee that a published installation has been updated.

Missing panels remain distinct from captured empty arrays; failure envelopes take precedence over payloads, and
state-only transitions still count as one change without discarding unchanged leaves. Transitions with structural
additions, removals, or changes do not increment the changed count again. Observed configured IDs retain label order;
extras retain regular ascending sorting and ID labels. Unknown configured IDs do not create rows.
Comparison does not apply capture-policy redaction to Logs.

Use the additive metric factory when configuring a panel link fluently:

```php
use Yii3\Debug\Comparison\{HistoryMetricComparison, HistoryMetricValues};

$metric = HistoryMetricComparison::create(
    'Duration',
    new HistoryMetricValues('10.00 ms', '15.00 ms', '+5.00 ms (+50.0%)', 'up'),
)->withPanelId('profiling');
```

The constructor remains supported. `withPanelId()` returns a copy; `null` clears the link and `''` remains an empty ID.
