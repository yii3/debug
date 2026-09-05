<?php

declare(strict_types=1);

return [
    '/' => [
        'params' => [
            '//' => ['config/params.php'],
            '/' => ['params.php'],
        ],
        'di' => [
            'tests/Support/Config' => ['vendor.php'],
            '//' => ['config/di.php'],
            '/' => ['di.php'],
        ],
        'di-web' => [
            '//' => ['config/di-web.php'],
            '/' => ['$di'],
        ],
        'routes' => ['//' => ['config/routes.php']],
    ],
    'debug' => [],
    'dev' => [],
    'test' => [],
    'prod' => [],
];
