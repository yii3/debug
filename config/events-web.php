<?php

declare(strict_types=1);

use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Extension\ProviderCatalog;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

return [
    ApplicationShutdown::class => [
        [DeferredCapture::class, 'finalize'],
    ],
    ...ProviderCatalog::packaged()->listeners(),
];
