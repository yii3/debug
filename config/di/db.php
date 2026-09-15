<?php

declare(strict_types=1);

use Yii3\Debug\Db\{DbExplain, DebugDbProfiler};
use Yii3\Debug\Panel\DbPanel;
use Yii3\Debug\Web\DebugUrlGenerator;
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Db\Profiler\ProfilerInterface as DbProfilerInterface;
use Yiisoft\Definitions\Reference;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    DbExplain::class => [
        '__construct()' => [
            'connection' => Reference::optional(ConnectionInterface::class),
        ],
    ],
    DbPanel::class => [
        '__construct()' => [
            'urls' => new DebugUrlGenerator($config['routePrefix']),
        ],
        'withThresholds()' => [
            $config['database']['criticalQueryThreshold'],
            $config['database']['excessiveCallerThreshold'],
        ],
    ],
    DbProfilerInterface::class => DebugDbProfiler::class,
];
