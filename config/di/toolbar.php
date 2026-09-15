<?php

declare(strict_types=1);

use PHPForge\Debug\Capture\CapturePolicy;
use PHPForge\Debug\Collector\CollectorCoordinator;
use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\ExtensionRegistry;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Panel\{DbPanel, EventPanel, LogPanel, ProfilingPanel, RequestPanel};
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\{DebugRequestHandler, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\{DynamicReference, Reference};
use Yiisoft\NetworkUtilities\IpRanges;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    DebugRequestHandler::class => [
        'withRoutePrefix()' => [$config['routePrefix']],
    ],
    ToolbarDataFactory::class => [
        'withExtensionPanels()' => [
            DynamicReference::to(
                static fn(
                    ExtensionRegistry $extensions,
                    RequestPanel $requestPanel,
                    LogPanel $logPanel,
                    EventPanel $eventPanel,
                    ProfilingPanel $profilingPanel,
                    DbPanel $dbPanel,
                ): array => $extensions->panelsWithBuiltIns(
                    [$requestPanel, $logPanel, $eventPanel, $profilingPanel, $dbPanel],
                ),
            ),
        ],
        'withRoutePrefix()' => [$config['routePrefix']],
        'withPresentation()' => [$config['toolbar']['position'], $config['toolbar']['height']],
    ],
    ToolbarMiddleware::class => [
        '__construct()' => [
            'allowedIpRanges' => new IpRanges($config['allowedIPs']),
        ],
        'withCollectorCoordinator()' => [Reference::to(CollectorCoordinator::class)],
        'withCapturePolicy()' => [Reference::to(CapturePolicy::class)],
        'withDebugRequestHandler()' => [Reference::to(DebugRequestHandler::class)],
        'withDeferredCapture()' => [Reference::to(DeferredCapture::class)],
        'withRoutePrefix()' => [$config['routePrefix']],
        'withHistorySize()' => [$config['historySize']],
        'withExcessiveCallerThreshold()' => [$config['database']['excessiveCallerThreshold']],
        'withSkipUrls()' => [$config['toolbar']['skipUrls']],
        'withPresentation()' => [$config['toolbar']['position'], $config['toolbar']['height']],
    ],
    ToolbarRenderer::class => [
        '__construct()' => [
            'viewPath' => DynamicReference::to(
                static fn(Aliases $aliases): string => $aliases->get($config['viewPath']),
            ),
        ],
    ],
];
