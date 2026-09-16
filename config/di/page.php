<?php

declare(strict_types=1);

use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry};
use Yii3\Debug\Panel\{AssetPanel, DbPanel, EventPanel, LogPanel, ProfilingPanel, RequestPanel};
use Yii3\Debug\Web\DebugPageRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Definitions\DynamicReference;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    ConfigDataFactory::class => [
        '__construct()' => [
            'application' => $config['application'],
        ],
    ],
    DebugPageRenderer::class => [
        '__construct()' => [
            'viewPath' => DynamicReference::to(
                static fn(Aliases $aliases): string => $aliases->get($config['viewPath']),
            ),
        ],
        'withExtensionPanels()' => [
            DynamicReference::to(
                static fn(
                    ExtensionRegistry $extensions,
                    RequestPanel $requestPanel,
                    LogPanel $logPanel,
                    EventPanel $eventPanel,
                    ProfilingPanel $profilingPanel,
                    DbPanel $dbPanel,
                    AssetPanel $assetPanel,
                ): array => $extensions->panelsWithBuiltIns(
                    [$requestPanel, $logPanel, $eventPanel, $profilingPanel, $dbPanel, $assetPanel],
                ),
            ),
        ],
        'withRoutePrefix()' => [$config['routePrefix']],
    ],
];
