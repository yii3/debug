<?php

declare(strict_types=1);

use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\StreamFactoryInterface;
use Yii3\Debug\ConfigDataFactory;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\{DebugPageRenderer, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\View\WebView;

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    ConfigDataFactory::class => [
        '__construct()' => [
            'application' => $config['application'],
        ],
    ],
    DebugPageRenderer::class => static fn(
        WebView $view,
        AssetManager $assetManager,
        ConfigDataFactory $configDataFactory,
        Aliases $aliases,
    ): DebugPageRenderer => (
        new DebugPageRenderer(
            $view,
            $assetManager,
            $configDataFactory,
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
        )
    )
    ->withRoutePrefix($config['routePrefix']),
    SnapshotStore::class => static fn(Aliases $aliases): SnapshotStore => new SnapshotStore(
        path: $aliases->get($config['storage']['path']),
        dirMode: $config['storage']['dirMode'],
        fileMode: $config['storage']['fileMode'],
    ),
    ToolbarDataFactory::class => static fn(AssetManager $assetManager): ToolbarDataFactory => (
        new ToolbarDataFactory($assetManager)
    )
    ->withRoutePrefix($config['routePrefix'])
    ->withPresentation($config['toolbar']['position'], $config['toolbar']['height']),
    ToolbarMiddleware::class => static fn(
        ToolbarRenderer $renderer,
        StreamFactoryInterface $streamFactory,
        SnapshotStore $store,
    ): ToolbarMiddleware => (
        new ToolbarMiddleware(
            $renderer,
            $streamFactory,
            $store,
            new IpRanges($config['allowedIPs']),
        )
    )
    ->withRoutePrefix($config['routePrefix'])
    ->withHistorySize($config['historySize'])
    ->withSkipUrls($config['toolbar']['skipUrls'])
    ->withPresentation($config['toolbar']['position'], $config['toolbar']['height']),
    ToolbarRenderer::class => static fn(
        WebView $view,
        AssetManager $assetManager,
        Aliases $aliases,
    ): ToolbarRenderer => new ToolbarRenderer(
        $view,
        $assetManager,
        $aliases->get('@vendor/php-forge/debug-core/resources/views'),
    ),
];
