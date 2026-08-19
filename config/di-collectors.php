<?php

declare(strict_types=1);

use PHPForge\Debug\Collector\CollectorCoordinator;
use Yii3\Debug\Collector\{
    AssetCollector,
    ConfigCollector,
    DbCollector,
    DumpCollector,
    EventCollector,
    LogCollector,
    MailCollector,
    ProfilingCollector,
    QueueCollector,
    RequestCollector,
    RouterCollector,
    TimelineCollector,
    UserCollector,
};
use Yiisoft\Db\Profiler\ProfilerInterface as DbProfilerInterface;
use Yiisoft\Definitions\{DynamicReference, Reference, ReferencesArray};
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Profiler\ProfilerInterface as ApplicationProfilerInterface;
use Yiisoft\Queue\QueueProducerInterface;
use Yiisoft\Rbac\ManagerInterface;
use Yiisoft\User\CurrentUser;
use Yiisoft\Aliases\Aliases;

/**
 * @var array<string, mixed> $params
 */
$config = $params['yii3/debug'];

$hasDb = interface_exists(DbProfilerInterface::class);
$hasMail = interface_exists(MailerInterface::class);
$hasQueue = interface_exists(QueueProducerInterface::class);
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
    DumpCollector::class => [
        '__construct()' => [
            'depth' => $config['dump']['depth'],
            'highlight' => $config['dump']['highlight'],
        ],
    ],
    ...($hasMail ? [
        MailCollector::class => [
            '__construct()' => [
                'mailPath' => DynamicReference::to(
                    static fn(Aliases $aliases): string => $aliases->get($config['mail']['path']),
                ),
                'dirMode' => $config['dirMode'],
                'fileMode' => $config['fileMode'],
            ],
        ],
    ] : []),
    ...($hasQueue ? [
        QueueCollector::class => [
            '__construct()' => [
                'redactedProperties' => $config['queue']['redactedProperties'],
            ],
        ],
    ] : []),
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
                ...($hasMail ? [Reference::to(MailCollector::class)] : []),
                ...($hasQueue ? [Reference::to(QueueCollector::class)] : []),
                Reference::to(DumpCollector::class),
                Reference::to(AssetCollector::class),
                ...ReferencesArray::from($config['collectors']),
            ],
        ],
    ],
];
