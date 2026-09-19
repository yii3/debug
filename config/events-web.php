<?php

declare(strict_types=1);

use PHPForge\Inertia\Debug\InertiaCollector;
use PHPForge\Inertia\Event\ProtocolResultCreated;
use Yii3\Debug\Capture\DeferredCapture;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

$listeners = [
    ApplicationShutdown::class => [
        [DeferredCapture::class, 'finalize'],
    ],
];

// The protocol dispatches through the container, so the packaged collector observes every Inertia result.
if (class_exists(InertiaCollector::class)) {
    $listeners[ProtocolResultCreated::class] = [InertiaCollector::class];
}

return $listeners;
