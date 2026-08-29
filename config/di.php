<?php

declare(strict_types=1);

use PHPForge\Debug\Collector\CollectorCoordinator;
use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\{ResponseFactoryInterface, StreamFactoryInterface};
use Yii3\Debug\Action\ToolbarDataAction;
use Yii3\Debug\{ConfigDataFactory, ExtensionRegistry};
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
    CollectorCoordinator::class => static fn(
        ExtensionRegistry $extensions,
    ): CollectorCoordinator => new CollectorCoordinator($extensions->collectors()),
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
        ExtensionRegistry $extensions,
    ): DebugPageRenderer => (
        new DebugPageRenderer(
            $view,
            $assetManager,
            $configDataFactory,
            $aliases->get('@vendor/php-forge/debug-core/resources/views'),
            extensionPanels: $extensions->panels(),
        )
    )
    ->withRoutePrefix($config['routePrefix']),
    SnapshotStore::class => static fn(Aliases $aliases): SnapshotStore => new SnapshotStore(
        path: $aliases->get($config['storage']['path']),
        dirMode: $config['storage']['dirMode'],
        fileMode: $config['storage']['fileMode'],
    ),
    ToolbarDataAction::class => static fn(
        ToolbarDataFactory $dataFactory,
        ResponseFactoryInterface $responseFactory,
        StreamFactoryInterface $streamFactory,
        SnapshotStore $store,
    ): ToolbarDataAction => new ToolbarDataAction(
        $dataFactory,
        $responseFactory,
        $streamFactory,
        $store,
    ),
    ToolbarDataFactory::class => static fn(
        AssetManager $assetManager,
        ExtensionRegistry $extensions,
    ): ToolbarDataFactory => (
        new ToolbarDataFactory($assetManager, extensionPanels: $extensions->panels())
    )
    ->withRoutePrefix($config['routePrefix'])
    ->withPresentation($config['toolbar']['position'], $config['toolbar']['height']),
    ToolbarMiddleware::class => static fn(
        ToolbarRenderer $renderer,
        StreamFactoryInterface $streamFactory,
        SnapshotStore $store,
        CollectorCoordinator $collectorCoordinator,
    ): ToolbarMiddleware => (
        new ToolbarMiddleware(
            $renderer,
            $streamFactory,
            $store,
            new IpRanges($config['allowedIPs']),
            collectorCoordinator: $collectorCoordinator,
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
