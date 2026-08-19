<?php

declare(strict_types=1);

use PHPForge\Debug\Collector\CollectorCoordinator;
use Yii3\Debug\Collector\{
    AssetCollector,
    ConfigCollector,
    DbCollector,
    EventCollector,
    LogCollector,
    ProfilingCollector,
    RequestCollector,
    RouterCollector,
    TimelineCollector,
    UserCollector,
};
use Yiisoft\Db\Profiler\ProfilerInterface as DbProfilerInterface;
use Yiisoft\Definitions\{Reference, ReferencesArray};
use Yiisoft\Profiler\ProfilerInterface as ApplicationProfilerInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\User\CurrentUser;

/**
 * @var array<string, mixed> $params
 */
$config = $params['yii3/debug'];

$hasDb = interface_exists(DbProfilerInterface::class);
$hasUser = class_exists(CurrentUser::class);

return [
    ...($hasDb ? [DbProfilerInterface::class => Reference::to(DbCollector::class)] : []),
    DbCollector::class => [
        '__construct()' => [
            'profiler' => Reference::to(ApplicationProfilerInterface::class),
        ],
    ],
    ProfilingCollector::class => [
        '__construct()' => [
            'profiler' => Reference::to(ApplicationProfilerInterface::class),
        ],
    ],
    ...($hasUser ? [
        UserCollector::class => [
            '__construct()' => [
                'rbacManager' => Reference::optional(ManagerInterface::class),
            ],
        ],
    ] : []),
    CollectorCoordinator::class => [
        '__construct()' => [
            'collectors' => [
                Reference::to(ConfigCollector::class),
                Reference::to(RequestCollector::class),
                Reference::to(RouterCollector::class),
                ...($hasUser ? [Reference::to(UserCollector::class)] : []),
                Reference::to(LogCollector::class),
                ...($hasDb ? [Reference::to(DbCollector::class)] : []),
                Reference::to(ProfilingCollector::class),
                Reference::to(TimelineCollector::class),
                Reference::to(EventCollector::class),
                Reference::to(AssetCollector::class),
                ...ReferencesArray::from($config['collectors']),
            ],
        ],
    ],
];
