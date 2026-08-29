<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseFactoryInterface;
use Yii3\Debug\Action\{ConfigAction, HistoryAction, PhpInfoAction, ToolbarDataAction};
use Yiisoft\Router\{Group, Route};
use Yiisoft\Validator\ValidatorInterface;
use Yiisoft\Yii\Middleware\IpFilter;

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];
$prefix = rtrim($config['routePrefix'], '/');
$ipFilter = static fn(
    ResponseFactoryInterface $responseFactory,
    ValidatorInterface $validator,
): IpFilter => new IpFilter(
    validator: $validator,
    responseFactory: $responseFactory,
    ipRanges: $config['allowedIPs'],
);

return [
    Group::create($prefix)
        ->middleware($ipFilter)
        ->routes(
            Route::get('')
                ->action(HistoryAction::class)
                ->name('yii3-debug/history'),
            Route::get('/view')
                ->action(ConfigAction::class)
                ->name('yii3-debug/config'),
            Route::get('/php-info')
                ->action(PhpInfoAction::class)
                ->name('yii3-debug/php-info'),
            Route::get('/toolbar')
                ->action(ToolbarDataAction::class)
                ->name('yii3-debug/toolbar'),
        ),
];
