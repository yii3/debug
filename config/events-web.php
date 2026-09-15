<?php

declare(strict_types=1);

use PHPForge\Inertia\Debug\InertiaCollector;
use PHPForge\Inertia\Event\ProtocolResultCreated;
use PHPForge\Vite\Debug\ViteCollector;
use PHPForge\Vite\Event\AssetsResolved;
use Yii3\Debug\Capture\DeferredCapture;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

/** @var array<string, mixed> $params */
$extensions = $params['yii3/debug']['extensions'];

$listeners = [
    ApplicationShutdown::class => [
        [DeferredCapture::class, 'finalize'],
    ],
];

if ($extensions['inertia']) {
    $listeners[ProtocolResultCreated::class] = [InertiaCollector::class];
}

if ($extensions['vite']) {
    $listeners[AssetsResolved::class] = [ViteCollector::class];
}

return $listeners;
