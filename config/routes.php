<?php

declare(strict_types=1);

use Yii3\Debug\Action\{
    DbExplainAction,
    DownloadMailAction,
    HistoryAction,
    PhpInfoAction,
    QueueJobAction,
    ResetIdentityAction,
    SetIdentityAction,
    SnapshotAction,
    ToolbarDataAction,
};
use Yiisoft\Db\Connection\ConnectionInterface;
use Yiisoft\Mailer\MailerInterface;
use Yiisoft\Queue\QueueProducerInterface;
use Yiisoft\Router\Route;
use Yiisoft\User\CurrentUser;

/** @var array<string, mixed> $params */
$prefix = rtrim($params['yii3/debug']['routePrefix'], '/');

$routes = [
    Route::get($prefix)
        ->action(HistoryAction::class)
        ->name('yii3-debug/history'),
    Route::get($prefix . '/view')
        ->action(SnapshotAction::class)
        ->name('yii3-debug/view'),
    Route::get($prefix . '/toolbar')
        ->action(ToolbarDataAction::class)
        ->name('yii3-debug/toolbar'),
    Route::get($prefix . '/php-info')
        ->action(PhpInfoAction::class)
        ->name('yii3-debug/php-info'),
];

if (interface_exists(ConnectionInterface::class)) {
    $routes[] = Route::get($prefix . '/db-explain')
        ->action(DbExplainAction::class)
        ->name('yii3-debug/db-explain');
}

if (interface_exists(MailerInterface::class)) {
    $routes[] = Route::get($prefix . '/download-mail')
        ->action(DownloadMailAction::class)
        ->name('yii3-debug/download-mail');
}

if (interface_exists(QueueProducerInterface::class)) {
    $routes[] = Route::get($prefix . '/queue-job')
        ->action(QueueJobAction::class)
        ->name('yii3-debug/queue-job');
}

if (class_exists(CurrentUser::class)) {
    $routes[] = Route::post($prefix . '/set-identity')
        ->action(SetIdentityAction::class)
        ->name('yii3-debug/set-identity');
    $routes[] = Route::post($prefix . '/reset-identity')
        ->action(ResetIdentityAction::class)
        ->name('yii3-debug/reset-identity');
}

return $routes;
