<?php

declare(strict_types=1);

use Yii3\Debug\Middleware\ToolbarMiddleware;

return [
    'yii3/debug' => [
        'allowedIPs' => ['127.0.0.1', '::1'],
        'routePrefix' => '/debug',
        'toolbar' => [
            'skipUrls' => [],
            'position' => 'bottom',
            'height' => 50,
        ],
    ],
    'yiisoft/middleware-dispatcher' => [
        'middlewares' => [
            ToolbarMiddleware::class,
        ],
    ],
];
