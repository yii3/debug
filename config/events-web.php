<?php

declare(strict_types=1);

use Yii3\Debug\Capture\DeferredCapture;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

return [
    ApplicationShutdown::class => [
        [DeferredCapture::class, 'finalize'],
    ],
];
