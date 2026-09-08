<?php

declare(strict_types=1);

use Yii3\Debug\Log\DebugLogTarget;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yiisoft\Log\StreamTarget;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

return [
    'yii3/debug' => [
        'application' => [],
        'database' => [
            'criticalQueryThreshold' => null,
            'excessiveCallerThreshold' => null,
        ],
        'extensions' => [
            'inertia' => false,
            'vite' => false,
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
        'traceLine' => null,
        'tracePathMappings' => [],
    ],
    'yiisoft/aliases' => [
        'aliases' => [
            '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
        ],
    ],
    'yiisoft/log' => [
        'targets' => [
            'debug' => DebugLogTarget::class,
            'stream' => StreamTarget::class,
        ],
    ],
    'yiisoft/middleware-dispatcher' => [
        'middlewares' => [
            ToolbarMiddleware::class,
        ],
    ],
];
