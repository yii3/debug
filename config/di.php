<?php

declare(strict_types=1);

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Helper\Trace;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\{ResponseFactoryInterface, StreamFactoryInterface};
use Psr\Log\LoggerInterface;
use Yii3\Debug\Action\ToolbarDataAction;
use Yii3\Debug\Collector\{DbCollector, EventCollector, LogCollector, ProfilingCollector, RequestCollector};
use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry};
use Yii3\Debug\Db\{DbExplain, DebugDbProfiler};
use Yii3\Debug\Event\DebugEventDispatcher;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Panel\{DbPanel, EventPanel, LogPanel, ProfilingPanel, RequestPanel};
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\{DebugPageRenderer, DebugUrlGenerator,ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Profiler\ProfilerInterface as DbProfilerInterface;
use Yiisoft\Definitions\{Reference, ReferencesArray};
use Yiisoft\EventDispatcher\Dispatcher\Dispatcher;
use Yiisoft\Log\Logger;
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\Profiler\ProfilerInterface;
use Yiisoft\View\WebView;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    CollectorCoordinator::class => static fn(
        RequestCollector $requestCollector,
        LogCollector $logCollector,
        EventCollector $eventCollector,
        ProfilingCollector $profilingCollector,
        DbCollector $dbCollector,
        ExtensionRegistry $extensions,
    ): CollectorCoordinator => new CollectorCoordinator(
        $extensions->collectorsWithBuiltIns(
            [$requestCollector, $logCollector, $eventCollector, $profilingCollector, $dbCollector],
        ),
    ),
    ConfigDataFactory::class => [
        '__construct()' => [
            'application' => $config['application'],
        ],
    ],
    DebugPageRenderer::class => static fn(
        WebView $view,
        AssetManager $assetManager,
        ConfigDataFactory $configDataFactory,
        Aliases $aliases,
        RequestPanel $requestPanel,
        LogPanel $logPanel,
        EventPanel $eventPanel,
        ProfilingPanel $profilingPanel,
        DbPanel $dbPanel,
        ExtensionRegistry $extensions,
    ): DebugPageRenderer => (
        new DebugPageRenderer(
            $view,
            $assetManager,
            $configDataFactory,
            $aliases->get($config['viewPath']),
        )
    )
    ->withExtensionPanels(
        $extensions->panelsWithBuiltIns([$requestPanel, $logPanel, $eventPanel, $profilingPanel, $dbPanel]),
    )
    ->withRoutePrefix($config['routePrefix']),
    DbExplain::class => [
        '__construct()' => [
            'connection' => Reference::optional(ConnectionInterface::class),
        ],
    ],
    DbPanel::class => static fn(DbExplain $explain, Trace $trace): DbPanel => (
        new DbPanel(
            $explain,
            new DebugUrlGenerator($config['routePrefix']),
            $trace,
        )
    )->withThresholds($config['database']['criticalQueryThreshold'], $config['database']['excessiveCallerThreshold']),
    DbProfilerInterface::class => DebugDbProfiler::class,
    DebugDbProfiler::class => static fn(DbCollector $collector, ProfilerInterface $profiler): DebugDbProfiler => new DebugDbProfiler(
        $collector,
        $profiler,
    ),
    EventDispatcherInterface::class => static fn(
        Dispatcher $dispatcher,
        EventCollector $collector,
    ): EventDispatcherInterface => new DebugEventDispatcher($dispatcher, $collector),
    LoggerInterface::class => [
        'class' => Logger::class,
        '__construct()' => [
            'targets' => ReferencesArray::from($params['yiisoft/log']['targets']),
        ],
    ],
    LogPanel::class => static fn(Trace $trace): LogPanel => new LogPanel($trace),
    ProfilingCollector::class => static fn(
        ProfilerInterface $profiler,
    ): ProfilingCollector => new ProfilingCollector($profiler),
    SnapshotStore::class => static fn(Aliases $aliases): SnapshotStore => new SnapshotStore(
        path: $aliases->get($config['storage']['path']),
        dirMode: $config['storage']['dirMode'],
        fileMode: $config['storage']['fileMode'],
    ),
    ToolbarDataAction::class => static fn(
        ToolbarDataFactory $dataFactory,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        SnapshotStore $store,
    ): ToolbarDataAction => new ToolbarDataAction(
        $dataFactory,
        $responseFactory,
        $streamFactory,
        $store,
    ),
    ToolbarDataFactory::class => static fn(
        AssetManager $assetManager,
        RequestPanel $requestPanel,
        LogPanel $logPanel,
        EventPanel $eventPanel,
        ProfilingPanel $profilingPanel,
        DbPanel $dbPanel,
        ExtensionRegistry $extensions,
    ): ToolbarDataFactory => (
        new ToolbarDataFactory($assetManager)
    )
    ->withExtensionPanels(
        $extensions->panelsWithBuiltIns([$requestPanel, $logPanel, $eventPanel, $profilingPanel, $dbPanel]),
    )
    ->withRoutePrefix($config['routePrefix'])
    ->withPresentation($config['toolbar']['position'], $config['toolbar']['height']),
    ToolbarMiddleware::class => static fn(
        ToolbarRenderer $renderer,
        StreamFactoryInterface $streamFactory,
        SnapshotStore $store,
        CollectorCoordinator $collectorCoordinator,
        CapturePolicy $capturePolicy,
    ): ToolbarMiddleware => (
        new ToolbarMiddleware(
            $renderer,
            $streamFactory,
            $store,
            new IpRanges($config['allowedIPs']),
        )
    )
    ->withCollectorCoordinator($collectorCoordinator)
    ->withCapturePolicy($capturePolicy)
    ->withRoutePrefix($config['routePrefix'])
    ->withHistorySize($config['historySize'])
    ->withSkipUrls($config['toolbar']['skipUrls'])
    ->withPresentation($config['toolbar']['position'], $config['toolbar']['height']),
    ToolbarRenderer::class => static fn(
        WebView $view,
        AssetManager $assetManager,
        Aliases $aliases,
    ): ToolbarRenderer => new ToolbarRenderer(
        $view,
        $assetManager,
        $aliases->get($config['viewPath']),
    ),
    Trace::class => static function () use ($config): Trace {
        $trace = Trace::create();

        if ($config['traceLine'] !== null) {
            $trace = $trace->withTemplate($config['traceLine']);
        }

        return $trace->withPathMappings($config['tracePathMappings']);
    },
];
