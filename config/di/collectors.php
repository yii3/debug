<?php

declare(strict_types=1);

use PHPForge\Debug\Collector\CollectorCoordinator;
use Yii3\Debug\Collector\{DbCollector, EventCollector, LogCollector, ProfilingCollector, RequestCollector};
use Yii3\Debug\ExtensionRegistry;

if (!(require dirname(__DIR__) . '/enabled.php')) {
    return [];
}

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
];
