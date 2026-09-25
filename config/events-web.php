<?php

declare(strict_types=1);

use Yii3\Debug\Capture\DeferredCapture;
use Yii3\Debug\Collector\MailCollector;
use Yiisoft\Mailer\Event\AfterSend;
use Yiisoft\Yii\Http\Event\ApplicationShutdown;

if (!(require __DIR__ . '/enabled.php')) {
    return [];
}

$events = [
    ApplicationShutdown::class => [
        [DeferredCapture::class, 'finalize'],
    ],
];

// `yiisoft/mailer` is optional; without it no `AfterSend` event is ever dispatched.
if (class_exists(AfterSend::class)) {
    $events[AfterSend::class] = [
        [MailCollector::class, 'collect'],
    ];
}

return $events;
