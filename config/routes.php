<?php

declare(strict_types=1);

use Yii3\Debug\Action\{
    HistoryAction,
    PhpInfoAction,
    ResetIdentityAction,
    SetIdentityAction,
    SnapshotAction,
    ToolbarDataAction,
};
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

if (class_exists(CurrentUser::class)) {
    $routes[] = Route::post($prefix . '/set-identity')
        ->action(SetIdentityAction::class)
        ->name('yii3-debug/set-identity');
    $routes[] = Route::post($prefix . '/reset-identity')
        ->action(ResetIdentityAction::class)
        ->name('yii3-debug/reset-identity');
}

return $routes;
