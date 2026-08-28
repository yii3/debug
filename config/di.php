<?php

declare(strict_types=1);

use Psr\Http\Message\StreamFactoryInterface;
use Yii3\Debug\Middleware\ToolbarMiddleware;
use Yii3\Debug\ToolbarDataFactory;
use Yii3\Debug\Web\ToolbarRenderer;
use Yiisoft\Aliases\Aliases;
use Yiisoft\Assets\AssetManager;
use Yiisoft\NetworkUtilities\IpRanges;
use Yiisoft\View\WebView;

/** @var array<string, mixed> $params */
$config = $params['yii3/debug'];

return [
    ToolbarDataFactory::class => [
        '__construct()' => [
            'position' => $config['toolbar']['position'],
            'height' => $config['toolbar']['height'],
        ],
    ],
    ToolbarMiddleware::class => static fn(
        ToolbarRenderer $renderer,
        StreamFactoryInterface $streamFactory,
    ): ToolbarMiddleware => new ToolbarMiddleware(
        renderer: $renderer,
        streamFactory: $streamFactory,
        allowedIpRanges: new IpRanges($config['allowedIPs']),
        routePrefix: $config['routePrefix'],
        skipUrls: $config['toolbar']['skipUrls'],
        position: $config['toolbar']['position'],
        height: $config['toolbar']['height'],
    ),
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
