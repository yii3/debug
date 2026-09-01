<?php

declare(strict_types=1);

use Yii3\Debug\Middleware\ToolbarMiddleware;

return [
    'yii3/debug' => [
        'application' => [],
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
    'yiisoft/aliases' => [
        'aliases' => [
            '@yii3DebugViews' => '@vendor/php-forge/debug-core/resources/views',
        ],
    ],
    'yiisoft/middleware-dispatcher' => [
        'middlewares' => [
            ToolbarMiddleware::class,
        ],
    ],
];
