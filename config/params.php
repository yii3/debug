<?php

declare(strict_types=1);

return [
    'yii3/debug' => [
        'path' => 'runtime/debug',
        'historySize' => 50,
        'dirMode' => 0o775,
        'fileMode' => 0o664,
        'application' => [],
        'collectors' => [],
        'panels' => [],
        'allowedIPs' => ['127.0.0.1', '::1'],
        'routePrefix' => '/debug',
        'userSwitch' => [
            'enabled' => false,
        ],
        'dump' => [
            'depth' => 10,
            'highlight' => true,
        ],
        'mail' => [
            'path' => 'runtime/debug/mail',
        ],
        'queue' => [
            'redactedProperties' => [
                'accessToken',
                'apiKey',
                'authorization',
                'password',
                'refreshToken',
                'secret',
                'token',
            ],
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
];
