<?php

declare(strict_types=1);

use PHPForge\Debug\Storage\SnapshotStore;
use Psr\Http\Message\StreamFactoryInterface;
use Yii3\Debug\Collector\{ConfigCollector, RequestCollector};
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\Panel\ConfigPanel;
use Yii3\Debug\Web\{LocalAccessChecker, ToolbarRenderer};
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\Definitions\Reference;
use Yiisoft\View\WebView;

/**
 * @var array<string, mixed> $params
 */
$config = $params['yii3/debug'];

return [
    SnapshotStore::class => static fn(): SnapshotStore => new SnapshotStore(
        path: $config['path'],
        dirMode: $config['dirMode'],
        fileMode: $config['fileMode'],
    ),
    ConfigCollector::class => static fn(): ConfigCollector => new ConfigCollector($config['application']),
    ConfigPanel::class => static fn(): ConfigPanel => new ConfigPanel($config['routePrefix']),
    LocalAccessChecker::class => static fn(): LocalAccessChecker => new LocalAccessChecker($config['allowedIPs']),
    ToolbarRenderer::class => static fn(
        WebView $view,
        AssetManager $assetManager,
        Aliases $aliases,
    ): ToolbarRenderer => new ToolbarRenderer(
        $view,
        $assetManager,
        $aliases->get($config['viewPath']),
    ),
    ToolbarMiddleware::class => static fn(
        \PHPForge\Debug\Collector\CollectorCoordinator $coordinator,
        RequestCollector $requestCollector,
        SnapshotStore $store,
        ToolbarRenderer $renderer,
        StreamFactoryInterface $streamFactory,
        LocalAccessChecker $accessChecker,
    ): ToolbarMiddleware => new ToolbarMiddleware(
        coordinator: $coordinator,
        requestCollector: $requestCollector,
        store: $store,
        renderer: $renderer,
        streamFactory: $streamFactory,
        accessChecker: $accessChecker,
        historySize: $config['historySize'],
        routePrefix: $config['routePrefix'],
        skipUrls: $config['toolbar']['skipUrls'],
        position: $config['toolbar']['position'],
        height: $config['toolbar']['height'],
    ),
];
