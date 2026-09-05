<?php

declare(strict_types=1);

use PHPForge\Vite\Configuration\DevelopmentConfiguration;
use Yiisoft\Log\StreamTarget;

return [
    'php-forge/vite' => [
        'configuration' => DevelopmentConfiguration::create('http://127.0.0.1:5173'),
        'entrypoints' => ['resources/js/app.ts'],
    ],
    'yii3/debug' => [
        'extensions' => [
            'inertia' => true,
            'vite' => true,
        ],
    ],
    'yiisoft/log' => [
        'targets' => ['stream' => StreamTarget::class],
    ],
];
